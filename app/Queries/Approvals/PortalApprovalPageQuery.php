<?php

namespace App\Queries\Approvals;

use App\Authorization\ApprovalDataScope;
use App\Enums\System\ApprovalAction;
use App\Enums\System\ApprovalModule;
use App\Enums\System\ApprovalStatus;
use App\Models\Auth\User;
use App\Models\System\Approval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class PortalApprovalPageQuery
{
    /**
     * Caller must authorize Approval::viewAny before invoking this method.
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function execute(User $user, array $filters): array
    {
        // Same scoped query used by the generic API, never an unscoped Approval::query().
        $base = ApprovalDataScope::query($user);
        $canViewAll = $user->hasPermission('approval.view.all');
        $scope = $filters['scope'] ?? ($canViewAll ? 'all' : 'mine');

        if ($scope === 'mine' || ! $canViewAll) {
            $base->where('requested_by', $user->id);
            $scope = 'mine';
        }

        foreach (['module', 'action'] as $field) {
            if (! empty($filters[$field])) {
                $base->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['from'])) {
            $base->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $base->whereDate('created_at', '<=', $filters['to']);
        }
        if (! empty($filters['search'])) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $base->where(function (Builder $query) use ($term): void {
                $query->where('reason', 'like', $term)
                    ->orWhereHas('requester', fn (Builder $users) =>
                        $users->where('name', 'like', $term));
            });
        }

        // Count each status in the SAME authorized search/module/date cohort.
        $countsQuery = clone $base;
        $query = clone $base;
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        $grouped = $countsQuery->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');
        $counts = ['total' => 0];
        foreach (ApprovalStatus::cases() as $status) {
            $counts[strtolower($status->value)] = (int) ($grouped[$status->value] ?? 0);
            $counts['total'] += $counts[strtolower($status->value)];
        }

        $paginator = $query->with('requester')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 20))->withQueryString();

        return [
            'scope' => $scope,
            'can_view_all' => $canViewAll,
            'filters' => [
                'scope' => $scope,
                'status' => $filters['status'] ?? '',
                'module' => $filters['module'] ?? '',
                'action' => $filters['action'] ?? '',
                'search' => $filters['search'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'per_page' => (int) ($filters['per_page'] ?? 20),
            ],
            'options' => [
                'statuses' => $this->options(ApprovalStatus::cases()),
                'modules' => $this->options(ApprovalModule::cases()),
                'actions' => $this->options(ApprovalAction::cases()),
            ],
            'counts' => $counts,
            'records' => [
                'data' => $paginator->getCollection()->map(function (Approval $approval) use ($user): array {
                    // Explicit allowlist: no request_payload, internal IDs, or raw entity.
                    return [
                        'uuid' => $approval->uuid,
                        'reason' => $approval->reason,
                        'module' => [
                            'value' => $approval->module->value,
                            'label' => $approval->module->label(),
                        ],
                        'action' => [
                            'value' => $approval->action->value,
                            'label' => $approval->action->label(),
                        ],
                        'status' => [
                            'value' => $approval->status->value,
                            'label' => $approval->status->label(),
                        ],
                        'requester' => $approval->requester ? [
                            'uuid' => $approval->requester->uuid,
                            'name' => $approval->requester->name,
                        ] : null,
                        'created_at' => $approval->created_at?->toIso8601String(),
                        'can' => $this->decisionPermissions($user, $approval),
                    ];
                })->values()->all(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'previous' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];
    }

    /** @return array{approve: bool, reject: bool} */
    public function decisionPermissions(User $user, Approval $approval): array
    {
        $pending = $approval->status === ApprovalStatus::Pending;
        return [
            'approve' => $pending && Gate::forUser($user)->allows('approve', $approval),
            'reject' => $pending && Gate::forUser($user)->allows('reject', $approval),
        ];
    }

    /** @param array<int, \BackedEnum> $cases */
    private function options(array $cases): array
    {
        return array_map(static fn ($case): array => [
            'value' => $case->value,
            'label' => $case->label(),
        ], $cases);
    }
}

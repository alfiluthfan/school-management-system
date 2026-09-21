<?php

namespace App\Http\Controllers\Web;

use App\Enums\System\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\PortalApprovalIndexRequest;
use App\Http\Resources\Approvals\ApprovalResource;
use App\Models\System\Approval;
use App\Queries\Approvals\PortalApprovalPageQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PortalApprovalController extends Controller
{
    public function index(PortalApprovalIndexRequest $request, PortalApprovalPageQuery $query): Response
    {
        Gate::authorize('viewAny', Approval::class);
        return Inertia::render('Approvals/Index', [
            'approvals' => fn (): array => $query->execute(
                $request->user(), $request->validated()
            ),
        ]);
    }

    public function show(Request $request, Approval $approval, PortalApprovalPageQuery $query): Response
    {
        Gate::authorize('view', $approval); // foreign record -> 404
        $approval->load(['requester', 'reviewer', 'entity']);
        $detail = ApprovalResource::make($approval)->resolve($request);
        $detail['request_payload'] = $this->safeChanges($approval->request_payload);
        $detail['can'] = $query->decisionPermissions($request->user(), $approval);
        $detail['timeline'] = [
            ['key' => 'submitted', 'title' => 'Pengajuan dibuat', 'at' => $approval->created_at?->toIso8601String(), 'actor' => $approval->requester?->name],
        ];
        if ($approval->status !== ApprovalStatus::Pending) {
            $detail['timeline'][] = [
                'key' => 'reviewed',
                'title' => match ($approval->status) {
                    ApprovalStatus::Approved => 'Pengajuan disetujui',
                    ApprovalStatus::Rejected => 'Pengajuan ditolak',
                    ApprovalStatus::Cancelled => 'Pengajuan dibatalkan',
                    default => 'Keputusan dicatat',
                },
                'at' => $approval->reviewed_at?->toIso8601String(),
                'actor' => $approval->reviewer?->name,
            ];
        }
        return Inertia::render('Approvals/Show', ['approval' => $detail]);
    }

    /**
     * Do not expose arbitrary stored payload fields or relational bigint IDs.
     * @return array<string, mixed>
     */
    private function safeChanges(?array $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }
        $allowed = ['status', 'check_in_at', 'check_out_at', 'notes', 'amount',
            'payment_method', 'reference_number'];
        if (! isset($payload['changes']) || ! is_array($payload['changes'])) {
            return [];
        }
        return ['changes' => array_intersect_key($payload['changes'], array_flip($allowed))];
    }
}

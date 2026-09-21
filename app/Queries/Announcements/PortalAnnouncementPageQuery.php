<?php
namespace App\Queries\Announcements;

use App\Enums\Communication\AnnouncementStatus;
use App\Enums\Communication\AnnouncementTargetScope;
use App\Models\Academic\SchoolClass;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Communication\Announcement;
use App\Services\Announcements\AnnouncementAudienceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class PortalAnnouncementPageQuery
{
    public function __construct(private readonly AnnouncementAudienceService $audience) {}

    /** @param array<string,mixed> $filters */
    public function index(User $user, array $filters): array
    {
        $manage = $this->canManage($user);
        $tab = $filters['tab'] ?? 'feed';
        abort_unless($tab !== 'mine' || $manage, 403);

        // IMPORTANT: feed never queries all announcements. The existing service
        // already enforces role overlap, active class enrollment, publish and expiry.
        $query = $tab === 'mine' ? $this->audience->mine($user) : $this->audience->feedFor($user);
        $query->with(['creator', 'schoolClass']);
        if (! empty($filters['search'])) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('title', 'like', $term)->orWhere('content', 'like', $term);
            });
        }
        if (! empty($filters['target_scope'])) $query->where('target_scope', $filters['target_scope']);

        $counts = null;
        if ($tab === 'mine') {
            $grouped = (clone $query)->selectRaw('status, COUNT(*) AS aggregate')
                ->groupBy('status')->pluck('aggregate', 'status');
            $counts = ['total' => 0];
            foreach (AnnouncementStatus::cases() as $case) {
                $counts[strtolower($case->value)] = (int) ($grouped[$case->value] ?? 0);
                $counts['total'] += $counts[strtolower($case->value)];
            }
            if (! empty($filters['status'])) $query->where('status', $filters['status']);
        }
        $records = $query->orderByDesc($tab === 'mine' ? 'created_at' : 'publish_at')
            ->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 20))->withQueryString();

        return [
            'tab' => $tab,
            'tabs' => $manage ? [
                ['value' => 'feed', 'label' => 'Untuk saya'],
                ['value' => 'mine', 'label' => 'Pengumuman saya'],
            ] : [['value' => 'feed', 'label' => 'Untuk saya']],
            'can' => ['manage' => $manage, 'create' => $this->canCreate($user)],
            'filters' => [
                'tab' => $tab, 'search' => $filters['search'] ?? '',
                'target_scope' => $filters['target_scope'] ?? '',
                'status' => $tab === 'mine' ? ($filters['status'] ?? '') : '',
                'per_page' => (int) ($filters['per_page'] ?? 20),
            ],
            'options' => [
                'scopes' => $this->options(AnnouncementTargetScope::cases()),
                'statuses' => $this->options(AnnouncementStatus::cases()),
            ],
            'counts' => $counts,
            'records' => [
                'data' => $records->getCollection()->map(fn (Announcement $a): array => [
                    ...$this->summary($a),
                    'excerpt' => Str::limit($a->content, 190),
                    'can' => $this->recordPermissions($user, $a),
                ])->values()->all(),
                'total' => $records->total(), 'from' => $records->firstItem(),
                'to' => $records->lastItem(), 'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(), 'previous' => $records->previousPageUrl(),
                'next' => $records->nextPageUrl(),
            ],
        ];
    }

    public function detail(User $user, Announcement $announcement): array
    {
        $announcement->load(['creator', 'schoolClass']);
        $own = $announcement->created_by === $user->id;
        // Target roles are editor-only; recipient feed needs only title/content/scope.
        if ($own) $announcement->load('roles');
        return [
            ...$this->summary($announcement),
            'content' => $announcement->content,
            'target_roles' => $own ? $announcement->roles->pluck('name')->values()->all() : null,
            'mine' => $own,
            'can' => $this->recordPermissions($user, $announcement),
        ];
    }

    public function formOptions(User $user, bool $forEdit = false): array
    {
        abort_unless($forEdit || $this->canCreate($user), 403);
        $school = Gate::forUser($user)->allows('createSchool', Announcement::class);
        $classes = collect();
        if ($user->hasPermission('announcement.create.class')) {
            if ($user->hasPermission('class.view.all')) {
                $classes = SchoolClass::query()->orderBy('name')->get();
            } elseif ($user->teacher) {
                $classes = SchoolClass::query()->where('homeroom_teacher_id', $user->teacher->id)
                    ->orderBy('name')->get();
            }
        }
        return [
            'can_school' => $school,
            'classes' => $classes->filter(fn (SchoolClass $class): bool =>
                Gate::forUser($user)->allows('createForClass', [Announcement::class, $class]))
                ->map(fn (SchoolClass $class): array => [
                    'uuid' => $class->uuid, 'name' => $class->name, 'code' => $class->code,
                ])->values()->all(),
            'roles' => Role::query()->orderBy('name')->get(['name', 'display_name'])
                ->map(fn (Role $role): array => [
                    'name' => $role->name, 'label' => $role->display_name ?: ucfirst($role->name),
                ])->values()->all(),
        ];
    }

    public function recordPermissions(User $user, Announcement $announcement): array
    {
        return [
            'update' => Gate::forUser($user)->allows('update', $announcement),
            'publish' => Gate::forUser($user)->allows('publish', $announcement),
            'archive' => Gate::forUser($user)->allows('archive', $announcement),
        ];
    }

    private function summary(Announcement $a): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        return [
            'uuid' => $a->uuid, 'title' => $a->title,
            'scope' => ['value' => $a->target_scope->value, 'label' => $a->target_scope->label()],
            'status' => ['value' => $a->status->value, 'label' => $a->status->label()],
            'visibility' => $a->status === AnnouncementStatus::Published
                ? ($a->publish_at?->greaterThan($now) ? 'scheduled'
                    : ($a->expired_at && $a->expired_at->lessThanOrEqualTo($now) ? 'expired' : 'active'))
                : strtolower($a->status->value),
            'creator' => $a->creator ? ['uuid' => $a->creator->uuid, 'name' => $a->creator->name] : null,
            'school_class' => $a->schoolClass ? [
                'uuid' => $a->schoolClass->uuid, 'name' => $a->schoolClass->name,
                'code' => $a->schoolClass->code,
            ] : null,
            'publish_at' => $a->publish_at?->toIso8601String(),
            'expired_at' => $a->expired_at?->toIso8601String(),
            'created_at' => $a->created_at?->toIso8601String(),
            'updated_at' => $a->updated_at?->toIso8601String(),
        ];
    }

    private function canManage(User $user): bool
    {
        return $user->hasAnyPermission([
            'announcement.create.school', 'announcement.create.class',
            'announcement.update.own', 'announcement.publish', 'announcement.delete.own',
        ]);
    }

    private function canCreate(User $user): bool
    {
        return Gate::forUser($user)->allows('createSchool', Announcement::class)
            || $this->formHasClassAccess($user);
    }

    private function formHasClassAccess(User $user): bool
    {
        if (! $user->hasPermission('announcement.create.class')) return false;
        if ($user->hasPermission('class.view.all')) return true;
        return $user->teacher !== null && SchoolClass::query()
            ->where('homeroom_teacher_id', $user->teacher->id)->exists();
    }

    /** @param array<int,\BackedEnum> $cases */
    private function options(array $cases): array
    {
        return array_map(static fn ($case): array => [
            'value' => $case->value, 'label' => $case->label(),
        ], $cases);
    }
}

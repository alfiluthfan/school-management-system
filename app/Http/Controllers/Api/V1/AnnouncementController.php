<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Announcements\ArchiveAnnouncementAction;
use App\Actions\Announcements\CreateAnnouncementAction;
use App\Actions\Announcements\PublishAnnouncementAction;
use App\Actions\Announcements\UpdateAnnouncementAction;
use App\Enums\Communication\AnnouncementTargetScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Announcements\AnnouncementIndexRequest;
use App\Http\Requests\Announcements\PublishAnnouncementRequest;
use App\Http\Requests\Announcements\StoreAnnouncementRequest;
use App\Http\Requests\Announcements\UpdateAnnouncementRequest;
use App\Http\Resources\Announcements\AnnouncementResource;
use App\Models\Academic\SchoolClass;
use App\Models\Communication\Announcement;
use App\Services\Announcements\AnnouncementAudienceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AnnouncementController extends Controller
{
    public function index(
        AnnouncementIndexRequest $request,
        AnnouncementAudienceService $audience
    ): AnonymousResourceCollection {
        Gate::authorize(
            'viewAny',
            Announcement::class
        );

        $query = $audience
            ->feedFor($request->user())
            ->with([
                'creator',
                'schoolClass',
                'roles',
            ]);

        $this->applyFilters(
            $query,
            $request,
            allowStatus: false
        );

        return AnnouncementResource::collection(
            $query
                ->latest('publish_at')
                ->paginate(
                    $request->perPage()
                )
                ->withQueryString()
        );
    }

    public function mine(
        AnnouncementIndexRequest $request,
        AnnouncementAudienceService $audience
    ): AnonymousResourceCollection {
        Gate::authorize(
            'viewAny',
            Announcement::class
        );

        $query = $audience
            ->mine($request->user())
            ->with([
                'creator',
                'schoolClass',
                'roles',
            ]);

        $this->applyFilters(
            $query,
            $request,
            allowStatus: true
        );

        return AnnouncementResource::collection(
            $query
                ->latest('created_at')
                ->paginate(
                    $request->perPage()
                )
                ->withQueryString()
        );
    }

    public function show(
        Announcement $announcement
    ): AnnouncementResource {
        Gate::authorize(
            'view',
            $announcement
        );

        $announcement->load([
            'creator',
            'schoolClass',
            'roles',
        ]);

        return AnnouncementResource::make(
            $announcement
        );
    }

    public function store(
        StoreAnnouncementRequest $request,
        CreateAnnouncementAction $action
    ) {
        $scope =
            AnnouncementTargetScope::from(
                $request->validated(
                    'target_scope'
                )
            );

        $schoolClass = null;

        if (
            $scope
            === AnnouncementTargetScope::School
        ) {
            Gate::authorize(
                'createSchool',
                Announcement::class
            );
        } else {
            $schoolClass = SchoolClass::query()
                ->where(
                    'uuid',
                    $request->validated(
                        'class_uuid'
                    )
                )
                ->firstOrFail();

            Gate::authorize(
                'createForClass',
                [
                    Announcement::class,
                    $schoolClass,
                ]
            );
        }

        $expiredAt =
            $request->validated('expired_at')
                ? CarbonImmutable::parse(
                    $request->validated(
                        'expired_at'
                    ),
                    config('app.timezone')
                )
                : null;

        $announcement = $action->execute(
            actor: $request->user(),
            title:
                $request->validated('title'),
            content:
                $request->validated('content'),
            targetScope: $scope,
            schoolClass: $schoolClass,
            targetRoles:
                $request->validated(
                    'target_roles'
                ),
            expiredAt: $expiredAt
        );

        return AnnouncementResource::make(
            $announcement
        )
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateAnnouncementRequest $request,
        Announcement $announcement,
        UpdateAnnouncementAction $action
    ): AnnouncementResource {
        Gate::authorize(
            'update',
            $announcement
        );

        $announcement = $action->execute(
            actor: $request->user(),
            announcement: $announcement,
            changes: $request->validated()
        );

        return AnnouncementResource::make(
            $announcement
        );
    }

    public function publish(
        PublishAnnouncementRequest $request,
        Announcement $announcement,
        PublishAnnouncementAction $action
    ): AnnouncementResource {
        Gate::authorize(
            'publish',
            $announcement
        );

        $validated = $request->validated();

        $publishAt =
            isset($validated['publish_at'])
            && $validated['publish_at'] !== null
                ? CarbonImmutable::parse(
                    $validated['publish_at'],
                    config('app.timezone')
                )
                : null;

        $expiredAtWasProvided =
            array_key_exists(
                'expired_at',
                $validated
            );

        $expiredAt =
            $expiredAtWasProvided
            && $validated['expired_at']
                !== null
                ? CarbonImmutable::parse(
                    $validated['expired_at'],
                    config('app.timezone')
                )
                : null;

        $announcement = $action->execute(
            actor: $request->user(),
            announcement: $announcement,
            publishAt: $publishAt,
            expiredAtWasProvided:
                $expiredAtWasProvided,
            expiredAt: $expiredAt
        );

        return AnnouncementResource::make(
            $announcement
        );
    }

    public function archive(
        Announcement $announcement,
        ArchiveAnnouncementAction $action
    ): AnnouncementResource {
        Gate::authorize(
            'archive',
            $announcement
        );

        $announcement = $action->execute(
            actor: request()->user(),
            announcement: $announcement
        );

        return AnnouncementResource::make(
            $announcement
        );
    }

    private function applyFilters(
        Builder $query,
        AnnouncementIndexRequest $request,
        bool $allowStatus
    ): void {
        if ($request->filled('search')) {
            $search = '%'
                .$request->validated(
                    'search'
                )
                .'%';

            $query->where(
                function (
                    Builder $scope
                ) use ($search): void {
                    $scope
                        ->where(
                            'title',
                            'like',
                            $search
                        )
                        ->orWhere(
                            'content',
                            'like',
                            $search
                        );
                }
            );
        }

        if (
            $request->filled(
                'target_scope'
            )
        ) {
            $query->where(
                'target_scope',
                $request->validated(
                    'target_scope'
                )
            );
        }

        if (
            $allowStatus
            && $request->filled('status')
        ) {
            $query->where(
                'status',
                $request->validated(
                    'status'
                )
            );
        }
    }
}

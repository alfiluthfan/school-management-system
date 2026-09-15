<?php

namespace App\Services\Announcements;

use App\Enums\Academic\EnrollmentStatus;
use App\Enums\Communication\AnnouncementStatus;
use App\Enums\Communication\AnnouncementTargetScope;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\StudentClassEnrollment;
use App\Models\Auth\User;
use App\Models\Communication\Announcement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class AnnouncementAudienceService
{
    public function feedFor(User $user): Builder
    {
        $roleIds = $user->roles()
            ->pluck('roles.id');

        if ($roleIds->isEmpty()) {
            return Announcement::query()
                ->whereRaw('1 = 0');
        }

        $classIds = $this->classIdsFor($user);
        $now = CarbonImmutable::now(
            config('app.timezone')
        );

        return Announcement::query()
            ->where(
                'status',
                AnnouncementStatus::Published->value
            )
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', $now)
            ->where(function (
                Builder $query
            ) use ($now): void {
                $query
                    ->whereNull('expired_at')
                    ->orWhere(
                        'expired_at',
                        '>',
                        $now
                    );
            })
            ->whereHas(
                'roles',
                fn(
                    Builder $query
                ) => $query->whereIn(
                    'roles.id',
                    $roleIds
                )
            )
            ->where(function (
                Builder $query
            ) use ($classIds): void {
                $query->where(
                    'target_scope',
                    AnnouncementTargetScope::School->value
                );

                if ($classIds->isNotEmpty()) {
                    $query->orWhere(
                        function (
                            Builder $classQuery
                        ) use ($classIds): void {
                            $classQuery
                                ->where(
                                    'target_scope',
                                    AnnouncementTargetScope::Classroom->value
                                )
                                ->whereIn(
                                    'class_id',
                                    $classIds
                                );
                        }
                    );
                }
            });
    }

    public function visibleTo(User $user): Builder
    {
        $feed = $this->feedFor($user);

        return Announcement::query()
            ->where(function (
                Builder $query
            ) use ($user, $feed): void {
                $query->where(
                    'created_by',
                    $user->id
                )->orWhereIn(
                    'id',
                    $feed->select(
                        'announcements.id'
                    )
                );
            });
    }

    public function mine(User $user): Builder
    {
        return Announcement::query()
            ->where(
                'created_by',
                $user->id
            );
    }

    /**
     * @return Collection<int, int>
     */
    private function classIdsFor(
        User $user
    ): Collection {
        if ($user->hasPermission('class.view.all')) {
            return SchoolClass::query()
                ->pluck('id');
        }

        $classIds = collect();

        if ($user->teacher) {
            $classIds = $classIds->merge(
                SchoolClass::query()
                    ->where(
                        'homeroom_teacher_id',
                        $user->teacher->id
                    )
                    ->pluck('id')
            );
        }

        if ($user->student) {
            $classIds = $classIds->merge(
                StudentClassEnrollment::query()
                    ->where(
                        'student_id',
                        $user->student->id
                    )
                    ->where(
                        'status',
                        EnrollmentStatus::Active->value
                    )
                    ->pluck('class_id')
            );
        }

        if ($user->guardian) {
            $studentIds = $user->guardian
                ->students()
                ->pluck('students.id');

            if ($studentIds->isNotEmpty()) {
                $classIds = $classIds->merge(
                    StudentClassEnrollment::query()
                        ->whereIn(
                            'student_id',
                            $studentIds
                        )
                        ->where(
                            'status',
                            EnrollmentStatus::Active->value
                        )
                        ->pluck('class_id')
                );
            }
        }

        return $classIds
            ->filter()
            ->unique()
            ->values();
    }
}

<?php

namespace App\Policies;

use App\Enums\Communication\AnnouncementStatus;
use App\Models\Academic\SchoolClass;
use App\Models\Auth\User;
use App\Models\Communication\Announcement;
use App\Services\Announcements\AnnouncementAudienceService;
use Illuminate\Auth\Access\Response;

class AnnouncementPolicy
{
    public function __construct(
        private readonly AnnouncementAudienceService $audience
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(
            'announcement.view'
        );
    }

    public function view(
        User $user,
        Announcement $announcement
    ): Response {
        $visible = $this->audience
            ->visibleTo($user)
            ->whereKey($announcement->id)
            ->exists();

        return $visible
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function createSchool(User $user): bool
    {
        return $user->hasPermission(
            'announcement.create.school'
        );
    }

    public function createForClass(
        User $user,
        SchoolClass $schoolClass
    ): bool {
        if (
            ! $user->hasPermission(
                'announcement.create.class'
            )
        ) {
            return false;
        }

        if ($user->hasPermission('class.view.all')) {
            return true;
        }

        return $user->teacher
            && $schoolClass->homeroom_teacher_id
                === $user->teacher->id;
    }

    public function update(
        User $user,
        Announcement $announcement
    ): bool {
        return $user->hasPermission(
            'announcement.update.own'
        )
            && $announcement->created_by
                === $user->id
            && $announcement->status
                === AnnouncementStatus::Draft;
    }

    public function publish(
        User $user,
        Announcement $announcement
    ): bool {
        return $user->hasPermission(
            'announcement.publish'
        )
            && $announcement->created_by
                === $user->id
            && $announcement->status
                === AnnouncementStatus::Draft;
    }

    public function archive(
        User $user,
        Announcement $announcement
    ): bool {
        return $user->hasPermission(
            'announcement.delete.own'
        )
            && $announcement->created_by
                === $user->id
            && $announcement->status
                !== AnnouncementStatus::Archived;
    }
}

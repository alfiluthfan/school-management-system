<?php

namespace App\Policies;

use App\Models\Attendance\TeacherLeave;
use App\Models\Auth\User;
use Illuminate\Auth\Access\Response;

class TeacherLeavePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'teacher-leave.view.own',
            'teacher-leave.view.all',
        ]);
    }

    public function view(User $user, TeacherLeave $leave): Response
    {
        if ($user->hasPermission('teacher-leave.view.all')) {
            return Response::allow();
        }

        if (
            $user->hasPermission('teacher-leave.view.own')
            && $user->teacher
            && $leave->teacher_id === $user->teacher->id
        ) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('teacher-leave.create')
            && $user->teacher !== null;
    }

    public function cancel(User $user, TeacherLeave $leave): bool
    {
        return $user->hasPermission('teacher-leave.cancel.own')
            && $user->teacher
            && $leave->teacher_id === $user->teacher->id;
    }
}

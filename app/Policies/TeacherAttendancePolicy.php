<?php

namespace App\Policies;

use App\Models\Attendance\TeacherAttendance;
use App\Models\Auth\User;
use Illuminate\Auth\Access\Response;

class TeacherAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'teacher-attendance.view.all',
            'teacher-attendance.view.own',
        ]);
    }

    public function view(User $user, TeacherAttendance $attendance): Response
    {
        if ($user->hasPermission('teacher-attendance.view.all')) {
            return Response::allow();
        }

        if (
            $user->hasPermission('teacher-attendance.view.own')
            && $user->teacher
            && $attendance->teacher_id === $user->teacher->id
        ) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function update(User $user, TeacherAttendance $attendance): bool
    {
        return $user->hasPermission('teacher-attendance.update');
    }

    public function correct(User $user, TeacherAttendance $attendance): bool
    {
        return $user->hasPermission('teacher-attendance.correct');
    }
}

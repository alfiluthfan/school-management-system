<?php

namespace App\Policies;

use App\Models\Attendance\StudentAttendance;
use App\Models\Auth\User;
use Illuminate\Auth\Access\Response;

class StudentAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'student-attendance.view.all',
            'student-attendance.view.class',
            'student-attendance.view.own',
            'student-attendance.view.child',
        ]);
    }

    public function view(User $user, StudentAttendance $attendance): Response
    {
        if ($user->hasPermission('student-attendance.view.all')) {
            return Response::allow();
        }

        if (
            $user->hasPermission('student-attendance.view.own')
            && $attendance->student()->where('user_id', $user->id)->exists()
        ) {
            return Response::allow();
        }

        if (
            $user->hasPermission('student-attendance.view.child')
            && $user->guardian
            && $attendance->student
                ->guardians()
                ->whereKey($user->guardian->id)
                ->exists()
        ) {
            return Response::allow();
        }

        if (
            $user->hasPermission('student-attendance.view.class')
            && $user->teacher
            && $attendance->schoolClass
            && $attendance->schoolClass->homeroom_teacher_id === $user->teacher->id
        ) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    /**
     * Manual attendance entry.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('student-attendance.create.manual');
    }

    public function update(User $user, StudentAttendance $attendance): bool
    {
        if ($user->hasPermission('student-attendance.update')) {
            return true;
        }

        return $user->hasPermission('student-attendance.correct')
            && $this->canAccessClass($user, $attendance);
    }

    public function correct(User $user, StudentAttendance $attendance): bool
    {
        if ($user->hasPermission('student-attendance.correct')) {
            return $user->hasPermission('student-attendance.view.all')
                || $this->canAccessClass($user, $attendance);
        }

        return false;
    }

    private function canAccessClass(User $user, StudentAttendance $attendance): bool
    {
        return $user->teacher
            && $attendance->schoolClass
            && $attendance->schoolClass->homeroom_teacher_id === $user->teacher->id;
    }
}

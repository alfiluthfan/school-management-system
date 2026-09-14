<?php

namespace App\Policies;

use App\Models\Academic\SchoolClass;
use App\Models\Auth\User;
use Illuminate\Auth\Access\Response;

class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'class.view.all',
            'class.view.assigned',
            'class.view.own',
            'class.view.child',
        ]);
    }

    public function view(User $user, SchoolClass $schoolClass): Response
    {
        if ($user->hasPermission('class.view.all')) {
            return Response::allow();
        }

        if (
            $user->hasPermission('class.view.assigned')
            && $user->teacher
            && $schoolClass->homeroom_teacher_id === $user->teacher->id
        ) {
            return Response::allow();
        }

        if (
            $user->hasPermission('class.view.own')
            && $user->student
            && $schoolClass->enrollments()
                ->where('student_id', $user->student->id)
                ->where('status', 'ACTIVE')
                ->exists()
        ) {
            return Response::allow();
        }

        if (
            $user->hasPermission('class.view.child')
            && $user->guardian
            && $schoolClass->enrollments()
                ->where('status', 'ACTIVE')
                ->whereHas(
                    'student.guardians',
                    fn ($query) => $query->where('parents.id', $user->guardian->id)
                )
                ->exists()
        ) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('class.create');
    }

    public function update(User $user, SchoolClass $schoolClass): bool
    {
        return $user->hasPermission('class.update');
    }

    public function delete(User $user, SchoolClass $schoolClass): bool
    {
        return $user->hasPermission('class.delete');
    }
}

<?php

namespace App\Policies;

use App\Models\Academic\Student;
use App\Models\Auth\User;
use Illuminate\Auth\Access\Response;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'student.view.all',
            'student.view.class',
            'student.view.own',
            'student.view.child',
        ]);
    }

    public function view(User $user, Student $student): Response
    {
        if ($user->hasPermission('student.view.all')) {
            return Response::allow();
        }

        if (
            $user->hasPermission('student.view.own')
            && $student->user_id === $user->id
        ) {
            return Response::allow();
        }

        if (
            $user->hasPermission('student.view.child')
            && $this->isLinkedChild($user, $student)
        ) {
            return Response::allow();
        }

        if (
            $user->hasPermission('student.view.class')
            && $this->isStudentInTeacherClass($user, $student)
        ) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('student.create');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasPermission('student.update');
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->hasPermission('student.delete');
    }

    private function isLinkedChild(User $user, Student $student): bool
    {
        $guardian = $user->guardian;

        if (! $guardian) {
            return false;
        }

        return $guardian->students()
            ->whereKey($student->getKey())
            ->exists();
    }

    private function isStudentInTeacherClass(User $user, Student $student): bool
    {
        $teacher = $user->teacher;

        if (! $teacher) {
            return false;
        }

        return $student->enrollments()
            ->where('status', 'ACTIVE')
            ->whereHas(
                'schoolClass',
                fn ($query) => $query->where('homeroom_teacher_id', $teacher->id)
            )
            ->exists();
    }
}

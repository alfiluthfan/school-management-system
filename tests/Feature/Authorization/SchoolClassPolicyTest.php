<?php

namespace Tests\Feature\Authorization;

use App\Authorization\SchoolDataScope;
use Illuminate\Support\Facades\Gate;

class SchoolClassPolicyTest extends AuthorizationTestCase
{
    public function test_student_and_teacher_class_scope_is_enforced(): void
    {
        [$studentUser, $student] = $this->createStudentUser();
        [$teacherUser, $teacher] = $this->createTeacherUser();
        $year = $this->createAcademicYear();
        $assigned = $this->createSchoolClass($year, $teacher);
        $other = $this->createSchoolClass($year);
        $this->enrollStudent($student, $assigned);

        $this->assertTrue(Gate::forUser($studentUser)->allows('view', $assigned));
        $this->assertFalse(Gate::forUser($studentUser)->allows('view', $other));
        $this->assertTrue(Gate::forUser($teacherUser)->allows('view', $assigned));
        $this->assertFalse(Gate::forUser($teacherUser)->allows('view', $other));

        $ids = SchoolDataScope::schoolClasses($teacherUser)->pluck('id')->all();
        $this->assertContains($assigned->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }
}

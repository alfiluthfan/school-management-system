<?php

namespace Tests\Feature\Authorization;

use App\Authorization\SchoolDataScope;
use Illuminate\Support\Facades\Gate;

class AuthorizationSecurityRegressionTest extends AuthorizationTestCase
{
    public function test_permission_name_alone_never_grants_wrong_record_scope(): void
    {
        [$parentUser] = $this->createGuardianUser();
        [, $student] = $this->createStudentUser();
        $this->assertTrue($parentUser->hasPermission('student.view.child'));
        $this->assertFalse(Gate::forUser($parentUser)->allows('view', $student));

        [$teacherUser] = $this->createTeacherUser();
        $year = $this->createAcademicYear();
        $class = $this->createSchoolClass($year);
        $this->enrollStudent($student, $class);
        $this->assertTrue($teacherUser->hasPermission('student.view.class'));
        $this->assertFalse(Gate::forUser($teacherUser)->allows('view', $student));
    }

    public function test_empty_relationship_scope_returns_zero_rows(): void
    {
        [$parentUser] = $this->createGuardianUser();
        $this->createStudentUser();
        $this->assertTrue(SchoolDataScope::students($parentUser)->doesntExist());

        [$teacherUser] = $this->createTeacherUser();
        $this->assertTrue(SchoolDataScope::students($teacherUser)->doesntExist());
    }
}

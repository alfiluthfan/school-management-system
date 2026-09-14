<?php

namespace Tests\Feature\Authorization;

use App\Authorization\SchoolDataScope;
use Illuminate\Support\Facades\Gate;

class StudentPolicyTest extends AuthorizationTestCase
{
    public function test_student_can_view_self_but_not_another_student(): void
    {
        [$user, $student] = $this->createStudentUser();
        [, $other] = $this->createStudentUser();
        $this->assertTrue(Gate::forUser($user)->allows('view', $student));
        $response = Gate::forUser($user)->inspect('view', $other);
        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
    }

    public function test_parent_can_view_linked_child_but_not_unrelated_student(): void
    {
        [$user, $guardian] = $this->createGuardianUser();
        [, $child] = $this->createStudentUser();
        [, $unrelated] = $this->createStudentUser();
        $this->linkGuardianToStudent($guardian, $child);
        $this->assertTrue(Gate::forUser($user)->allows('view', $child));
        $response = Gate::forUser($user)->inspect('view', $unrelated);
        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
    }

    public function test_teacher_can_view_student_in_homeroom_class_only(): void
    {
        [$user, $teacher] = $this->createTeacherUser();
        [, $ownStudent] = $this->createStudentUser();
        [, $otherStudent] = $this->createStudentUser();
        $year = $this->createAcademicYear();
        $ownClass = $this->createSchoolClass($year, $teacher);
        $otherClass = $this->createSchoolClass($year);
        $this->enrollStudent($ownStudent, $ownClass);
        $this->enrollStudent($otherStudent, $otherClass);
        $this->assertTrue(Gate::forUser($user)->allows('view', $ownStudent));
        $response = Gate::forUser($user)->inspect('view', $otherStudent);
        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
    }

    public function test_principal_can_view_any_student(): void
    {
        $principal = $this->createPrincipalUser();
        [, $student] = $this->createStudentUser();
        $this->assertTrue(Gate::forUser($principal)->allows('view', $student));
    }

    public function test_scoped_student_queries_do_not_leak_rows(): void
    {
        [$studentUser, $student] = $this->createStudentUser();
        [, $other] = $this->createStudentUser();
        $ids = SchoolDataScope::students($studentUser)->pluck('id')->all();
        $this->assertContains($student->id, $ids);
        $this->assertNotContains($other->id, $ids);

        [$parentUser, $guardian] = $this->createGuardianUser();
        $this->linkGuardianToStudent($guardian, $student);
        $parentIds = SchoolDataScope::students($parentUser)->pluck('id')->all();
        $this->assertContains($student->id, $parentIds);
        $this->assertNotContains($other->id, $parentIds);
    }
}

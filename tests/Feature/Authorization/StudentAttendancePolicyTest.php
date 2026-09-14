<?php

namespace Tests\Feature\Authorization;

use App\Authorization\SchoolDataScope;
use Illuminate\Support\Facades\Gate;

class StudentAttendancePolicyTest extends AuthorizationTestCase
{
    public function test_student_parent_and_teacher_attendance_scopes_are_enforced(): void
    {
        [$studentUser, $student] = $this->createStudentUser();
        [, $otherStudent] = $this->createStudentUser();
        [$teacherUser, $teacher] = $this->createTeacherUser();
        [$parentUser, $guardian] = $this->createGuardianUser();
        $this->linkGuardianToStudent($guardian, $student);

        $year = $this->createAcademicYear();
        $ownClass = $this->createSchoolClass($year, $teacher);
        $otherClass = $this->createSchoolClass($year);
        $own = $this->createStudentAttendance($student, $ownClass);
        $other = $this->createStudentAttendance($otherStudent, $otherClass);

        $this->assertTrue(Gate::forUser($studentUser)->allows('view', $own));
        $this->assertFalse(Gate::forUser($studentUser)->allows('view', $other));
        $this->assertTrue(Gate::forUser($parentUser)->allows('view', $own));
        $this->assertFalse(Gate::forUser($parentUser)->allows('view', $other));
        $this->assertTrue(Gate::forUser($teacherUser)->allows('view', $own));
        $this->assertFalse(Gate::forUser($teacherUser)->allows('view', $other));

        $ids = SchoolDataScope::studentAttendances($studentUser)->pluck('id')->all();
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }
}

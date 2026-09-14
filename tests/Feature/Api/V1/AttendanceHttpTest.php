<?php

namespace Tests\Feature\Api\V1;

use Carbon\CarbonImmutable;

class AttendanceHttpTest extends HttpApiTestCase
{
    public function test_unauthenticated_user_gets_401_for_attendance_index(): void
    {
        $this->getJson(route('api.v1.student-attendances.index'))
            ->assertUnauthorized();
    }

    public function test_invalid_check_in_payload_returns_422(): void
    {
        [$user] = $this->createApiStudent();

        $this->actingAs($user)
            ->postJson(
                route('api.v1.student-attendances.check-in'),
                [
                    'latitude' => 100,
                    'longitude' => 200,
                    'accuracy' => -1,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'latitude',
                'longitude',
                'accuracy',
            ]);
    }

    public function test_student_can_check_in_and_receives_201(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-09-14 06:55:00',
                'Asia/Jakarta'
            )
        );

        [$user, $student] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $schoolClass = $this->createApiClass($year);
        $this->enrollApiStudent($student, $schoolClass);
        $location = $this->createApiSchoolLocation();
        $this->createApiStudentSchedule($year, $location);

        $response = $this->actingAs($user)
            ->postJson(
                route('api.v1.student-attendances.check-in'),
                [
                    'latitude' => -6.2000000,
                    'longitude' => 106.8000000,
                    'accuracy' => 10,
                ]
            )
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'PRESENT')
            ->assertJsonPath('data.late_minutes', 0);

        $uuid = $response->json('data.uuid');

        $this->assertDatabaseHas('student_attendances', [
            'uuid' => $uuid,
            'student_id' => $student->id,
            'status' => 'PRESENT',
        ]);
    }

    public function test_parent_cannot_use_student_check_in_endpoint(): void
    {
        [$parentUser] = $this->createApiParent();

        $this->actingAs($parentUser)
            ->postJson(
                route('api.v1.student-attendances.check-in'),
                [
                    'latitude' => -6.2000000,
                    'longitude' => 106.8000000,
                    'accuracy' => 10,
                ]
            )
            ->assertForbidden();
    }

    public function test_student_gets_404_when_opening_another_students_attendance(): void
    {
        [$user] = $this->createApiStudent();
        [, $otherStudent] = $this->createApiStudent();

        $year = $this->createApiAcademicYear();
        $otherClass = $this->createApiClass($year);
        $attendance = $this->createApiAttendance(
            $otherStudent,
            $otherClass
        );

        $this->actingAs($user)
            ->getJson(
                route(
                    'api.v1.student-attendances.show',
                    $attendance
                )
            )
            ->assertNotFound();
    }

    public function test_student_attendance_index_does_not_leak_other_students(): void
    {
        [$user, $student] = $this->createApiStudent();
        [, $otherStudent] = $this->createApiStudent();

        $year = $this->createApiAcademicYear();
        $ownClass = $this->createApiClass($year);
        $otherClass = $this->createApiClass($year);

        $own = $this->createApiAttendance($student, $ownClass);
        $other = $this->createApiAttendance(
            $otherStudent,
            $otherClass
        );

        $this->actingAs($user)
            ->getJson(route('api.v1.student-attendances.index'))
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $own->uuid,
            ])
            ->assertJsonMissing([
                'uuid' => $other->uuid,
            ]);
    }

    public function test_parent_attendance_index_only_contains_linked_child(): void
    {
        [$parentUser, $guardian] = $this->createApiParent();
        [, $child] = $this->createApiStudent();
        [, $otherStudent] = $this->createApiStudent();

        $this->linkApiParentToStudent($guardian, $child);

        $year = $this->createApiAcademicYear();
        $childClass = $this->createApiClass($year);
        $otherClass = $this->createApiClass($year);

        $childAttendance = $this->createApiAttendance(
            $child,
            $childClass
        );
        $otherAttendance = $this->createApiAttendance(
            $otherStudent,
            $otherClass
        );

        $this->actingAs($parentUser)
            ->getJson(route('api.v1.student-attendances.index'))
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $childAttendance->uuid,
            ])
            ->assertJsonMissing([
                'uuid' => $otherAttendance->uuid,
            ]);
    }

    public function test_teacher_attendance_index_only_contains_homeroom_class(): void
    {
        [$teacherUser, $teacher] = $this->createApiTeacher();
        [, $ownStudent] = $this->createApiStudent();
        [, $otherStudent] = $this->createApiStudent();

        $year = $this->createApiAcademicYear();
        $ownClass = $this->createApiClass($year, $teacher);
        $otherClass = $this->createApiClass($year);

        $ownAttendance = $this->createApiAttendance(
            $ownStudent,
            $ownClass
        );
        $otherAttendance = $this->createApiAttendance(
            $otherStudent,
            $otherClass
        );

        $this->actingAs($teacherUser)
            ->getJson(route('api.v1.student-attendances.index'))
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $ownAttendance->uuid,
            ])
            ->assertJsonMissing([
                'uuid' => $otherAttendance->uuid,
            ]);
    }
}

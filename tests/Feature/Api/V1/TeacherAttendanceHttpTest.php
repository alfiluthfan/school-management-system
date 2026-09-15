<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Attendance\AttendanceSource;
use App\Enums\Attendance\AttendanceStatus;
use App\Enums\Attendance\AttendanceType;
use App\Enums\Attendance\TeacherLeaveStatus;
use App\Enums\Attendance\TeacherLeaveType;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Teacher;
use App\Models\Attendance\AttendanceSchedule;
use App\Models\Attendance\SchoolLocation;
use App\Models\Attendance\TeacherAttendance;
use App\Models\Attendance\TeacherLeave;
use Carbon\CarbonImmutable;

class TeacherAttendanceHttpTest extends HttpApiTestCase
{
    public function test_guest_gets_401_for_teacher_attendance_index(): void
    {
        $this->getJson(
            route(
                'api.v1.teacher-attendances.index'
            )
        )->assertUnauthorized();
    }

    public function test_student_cannot_use_teacher_check_in_endpoint(): void
    {
        [$studentUser] =
            $this->createApiStudent();

        $this->actingAs($studentUser)
            ->postJson(
                route(
                    'api.v1.teacher-attendances.check-in'
                ),
                $this->validLocationPayload()
            )
            ->assertForbidden();
    }

    public function test_invalid_teacher_check_in_payload_returns_422(): void
    {
        [$teacherUser] =
            $this->createApiTeacher();

        $this->actingAs($teacherUser)
            ->postJson(
                route(
                    'api.v1.teacher-attendances.check-in'
                ),
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

    public function test_teacher_can_check_in_and_receives_201(): void
    {
        $this->travelToMonday(
            '06:55:00'
        );

        [$teacherUser, $teacher] =
            $this->createApiTeacher();

        [$year, $location] =
            $this->makeTeacherAttendanceContext();

        $this->createTeacherSchedule(
            $year,
            $location
        );

        $response = $this
            ->actingAs($teacherUser)
            ->postJson(
                route(
                    'api.v1.teacher-attendances.check-in'
                ),
                $this->validLocationPayload()
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.teacher.uuid',
                $teacher->uuid
            )
            ->assertJsonPath(
                'data.status.value',
                'PRESENT'
            )
            ->assertJsonPath(
                'data.late_minutes',
                0
            );

        $uuid =
            $response->json('data.uuid');

        $this->assertDatabaseHas(
            'teacher_attendances',
            [
                'uuid' => $uuid,
                'teacher_id' => $teacher->id,
                'status' => 'PRESENT',
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' =>
                    $teacherUser->id,
                'module' =>
                    'attendance',
                'action' =>
                    'TEACHER_CHECK_IN',
            ]
        );
    }

    public function test_late_teacher_check_in_records_actual_late_minutes(): void
    {
        $this->travelToMonday(
            '07:20:00'
        );

        [$teacherUser] =
            $this->createApiTeacher();

        [$year, $location] =
            $this->makeTeacherAttendanceContext();

        $this->createTeacherSchedule(
            $year,
            $location
        );

        $this->actingAs($teacherUser)
            ->postJson(
                route(
                    'api.v1.teacher-attendances.check-in'
                ),
                $this->validLocationPayload()
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.status.value',
                'LATE'
            )
            ->assertJsonPath(
                'data.late_minutes',
                20
            );
    }

    public function test_teacher_check_in_outside_geofence_is_rejected(): void
    {
        $this->travelToMonday(
            '06:55:00'
        );

        [$teacherUser] =
            $this->createApiTeacher();

        [$year, $location] =
            $this->makeTeacherAttendanceContext();

        $this->createTeacherSchedule(
            $year,
            $location
        );

        $this->actingAs($teacherUser)
            ->postJson(
                route(
                    'api.v1.teacher-attendances.check-in'
                ),
                [
                    'latitude' => -6.2500000,
                    'longitude' => 106.8000000,
                    'accuracy' => 10,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'location'
            );

        $this->assertDatabaseCount(
            'teacher_attendances',
            0
        );
    }

    public function test_teacher_on_approved_leave_cannot_check_in(): void
    {
        $this->travelToMonday(
            '06:55:00'
        );

        [$teacherUser, $teacher] =
            $this->createApiTeacher();

        [$year, $location] =
            $this->makeTeacherAttendanceContext();

        $this->createTeacherSchedule(
            $year,
            $location
        );

        TeacherLeave::query()->create([
            'teacher_id' => $teacher->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
            'leave_type' =>
                TeacherLeaveType::Sick,
            'reason' =>
                'Sakit dan sudah disetujui.',
            'attachment_path' => null,
            'status' =>
                TeacherLeaveStatus::Approved,
        ]);

        $this->actingAs($teacherUser)
            ->postJson(
                route(
                    'api.v1.teacher-attendances.check-in'
                ),
                $this->validLocationPayload()
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'attendance'
            );

        $this->assertDatabaseCount(
            'teacher_attendances',
            0
        );
    }

    public function test_duplicate_teacher_check_in_is_rejected(): void
    {
        $this->travelToMonday(
            '06:55:00'
        );

        [$teacherUser] =
            $this->createApiTeacher();

        [$year, $location] =
            $this->makeTeacherAttendanceContext();

        $this->createTeacherSchedule(
            $year,
            $location
        );

        $url = route(
            'api.v1.teacher-attendances.check-in'
        );

        $this->actingAs($teacherUser)
            ->postJson(
                $url,
                $this->validLocationPayload()
            )
            ->assertCreated();

        $this->actingAs($teacherUser)
            ->postJson(
                $url,
                $this->validLocationPayload()
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'attendance'
            );

        $this->assertDatabaseCount(
            'teacher_attendances',
            1
        );
    }

    public function test_teacher_can_check_out_same_attendance(): void
    {
        $this->travelToMonday(
            '06:55:00'
        );

        [$teacherUser] =
            $this->createApiTeacher();

        [$year, $location] =
            $this->makeTeacherAttendanceContext();

        $this->createTeacherSchedule(
            $year,
            $location
        );

        $checkIn = $this
            ->actingAs($teacherUser)
            ->postJson(
                route(
                    'api.v1.teacher-attendances.check-in'
                ),
                $this->validLocationPayload()
            )
            ->assertCreated();

        $uuid =
            $checkIn->json('data.uuid');

        $this->travelToMonday(
            '14:30:00'
        );

        $this->actingAs($teacherUser)
            ->postJson(
                route(
                    'api.v1.teacher-attendances.check-out'
                ),
                $this->validLocationPayload()
            )
            ->assertOk()
            ->assertJsonPath(
                'data.uuid',
                $uuid
            )
            ->assertJsonPath(
                'data.check_out_at',
                '2026-09-14T14:30:00+07:00'
            );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' =>
                    $teacherUser->id,
                'module' =>
                    'attendance',
                'action' =>
                    'TEACHER_CHECK_OUT',
            ]
        );
    }

    public function test_teacher_index_only_contains_own_attendance(): void
    {
        [$teacherUser, $teacher] =
            $this->createApiTeacher();

        [, $otherTeacher] =
            $this->createApiTeacher();

        $own =
            $this->createTeacherAttendance(
                $teacher
            );

        $other =
            $this->createTeacherAttendance(
                $otherTeacher
            );

        $this->actingAs($teacherUser)
            ->getJson(
                route(
                    'api.v1.teacher-attendances.index'
                )
            )
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $own->uuid,
            ])
            ->assertJsonMissing([
                'uuid' => $other->uuid,
            ]);
    }

    public function test_teacher_gets_404_for_another_teachers_attendance(): void
    {
        [$teacherUser] =
            $this->createApiTeacher();

        [, $otherTeacher] =
            $this->createApiTeacher();

        $other =
            $this->createTeacherAttendance(
                $otherTeacher
            );

        $this->actingAs($teacherUser)
            ->getJson(
                route(
                    'api.v1.teacher-attendances.show',
                    $other
                )
            )
            ->assertNotFound();
    }

    public function test_principal_can_monitor_all_teacher_attendance(): void
    {
        $principal =
            $this->createApiPrincipal();

        [, $teacherA] =
            $this->createApiTeacher();

        [, $teacherB] =
            $this->createApiTeacher();

        $attendanceA =
            $this->createTeacherAttendance(
                $teacherA,
                '2026-09-14',
                AttendanceStatus::Present
            );

        $attendanceB =
            $this->createTeacherAttendance(
                $teacherB,
                '2026-09-15',
                AttendanceStatus::Late,
                20
            );

        $this->actingAs($principal)
            ->getJson(
                route(
                    'api.v1.teacher-attendances.index'
                )
            )
            ->assertOk()
            ->assertJsonFragment([
                'uuid' =>
                    $attendanceA->uuid,
            ])
            ->assertJsonFragment([
                'uuid' =>
                    $attendanceB->uuid,
            ]);
    }

    public function test_admin_can_filter_teacher_attendance_by_status_and_date(): void
    {
        $admin =
            $this->createApiAdmin();

        [, $teacherA] =
            $this->createApiTeacher();

        [, $teacherB] =
            $this->createApiTeacher();

        $present =
            $this->createTeacherAttendance(
                $teacherA,
                '2026-09-14',
                AttendanceStatus::Present
            );

        $late =
            $this->createTeacherAttendance(
                $teacherB,
                '2026-09-15',
                AttendanceStatus::Late,
                20
            );

        $this->actingAs($admin)
            ->getJson(
                route(
                    'api.v1.teacher-attendances.index',
                    [
                        'status' => 'LATE',
                        'from' => '2026-09-15',
                        'to' => '2026-09-15',
                    ]
                )
            )
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $late->uuid,
            ])
            ->assertJsonMissing([
                'uuid' => $present->uuid,
            ]);
    }

    /**
     * @return array{0: AcademicYear, 1: SchoolLocation}
     */
    private function makeTeacherAttendanceContext(): array
    {
        return [
            $this->createApiAcademicYear(),
            $this->createApiSchoolLocation(),
        ];
    }

    private function createTeacherSchedule(
        AcademicYear $year,
        SchoolLocation $location
    ): AttendanceSchedule {
        return AttendanceSchedule::query()
            ->create([
                'name' =>
                    'HTTP Teacher Schedule '
                    .$this->httpToken(
                        'teacher-schedule'
                    ),
                'attendance_type' =>
                    AttendanceType::Teacher,
                'day_of_week' => 1,
                'check_in_start' =>
                    '06:00:00',
                'check_in_deadline' =>
                    '07:00:00',
                'check_in_end' =>
                    '09:00:00',
                'check_out_start' =>
                    '14:00:00',
                'check_out_end' =>
                    '17:00:00',
                'late_tolerance_minutes' =>
                    5,
                'school_location_id' =>
                    $location->id,
                'academic_year_id' =>
                    $year->id,
                'is_active' => true,
                'effective_from' =>
                    '2026-07-01',
                'effective_until' =>
                    '2027-06-30',
            ]);
    }

    private function createTeacherAttendance(
        Teacher $teacher,
        string $date = '2026-09-14',
        AttendanceStatus $status =
            AttendanceStatus::Present,
        int $lateMinutes = 0
    ): TeacherAttendance {
        return TeacherAttendance::query()
            ->create([
                'teacher_id' =>
                    $teacher->id,
                'attendance_schedule_id' =>
                    null,
                'school_location_id' =>
                    null,
                'attendance_date' =>
                    $date,
                'check_in_at' =>
                    $date.' 06:55:00',
                'check_out_at' =>
                    null,
                'check_in_latitude' =>
                    '-6.2000000',
                'check_in_longitude' =>
                    '106.8000000',
                'check_out_latitude' =>
                    null,
                'check_out_longitude' =>
                    null,
                'location_accuracy' =>
                    '10.00',
                'distance_from_school' =>
                    '0.00',
                'status' =>
                    $status,
                'late_minutes' =>
                    $lateMinutes,
                'source' =>
                    AttendanceSource::Geolocation,
                'notes' =>
                    null,
            ]);
    }

    /**
     * @return array{
     *   latitude: float,
     *   longitude: float,
     *   accuracy: int
     * }
     */
    private function validLocationPayload(): array
    {
        return [
            'latitude' => -6.2000000,
            'longitude' => 106.8000000,
            'accuracy' => 10,
        ];
    }

    private function travelToMonday(
        string $time
    ): void {
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-09-14 '.$time,
                'Asia/Jakarta'
            )
        );
    }
}

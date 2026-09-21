<?php

namespace Tests\Feature\Web;

use App\Enums\Attendance\AttendanceSource;
use App\Enums\Attendance\AttendanceStatus;
use App\Enums\Attendance\TeacherLeaveStatus;
use App\Enums\Attendance\TeacherLeaveType;
use App\Models\Attendance\TeacherLeave;
use App\Models\Attendance\TeacherAttendance;
use App\Models\Auth\User;
use Carbon\CarbonImmutable;
use Tests\Feature\Api\V1\HttpApiTestCase;

final class PortalAttendanceTest extends HttpApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-14 06:55:00', 'Asia/Jakarta'));
    }

    private function inertiaGet(string $url)
    {
        $initial = $this->get($url)
            ->assertOk()
            ->assertViewHas('page');

        $version = data_get(
            $initial->viewData('page'),
            'version'
        );

        return $this->get($url, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version ?? '',
        ]);
    }

    private function gps(): array
    {
        return ['latitude' => -6.2, 'longitude' => 106.8, 'accuracy' => 10];
    }

    private function teacherRecord($teacher, string $date = '2026-09-14'): TeacherAttendance
    {
        return TeacherAttendance::query()->create([
            'teacher_id' => $teacher->id,
            'attendance_date' => $date,
            'check_in_at' => $date . ' 06:55:00',
            'check_in_latitude' => '-6.2000000',
            'check_in_longitude' => '106.8000000',
            'location_accuracy' => '10.00',
            'distance_from_school' => '0.00',
            'status' => AttendanceStatus::Present,
            'late_minutes' => 0,
            'source' => AttendanceSource::Geolocation,
        ]);
    }

    public function test_guest_redirected_and_unpermissioned_user_denied(): void
    {
        $this->get('/attendance')->assertRedirect('/login');
        $user = User::query()->create([
            'name' => 'No Attendance Access',
            'username' => $this->httpToken('no-attendance'),
            'email' => $this->httpToken('no-attendance') . '@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->actingAs($user, 'web')->get('/attendance')->assertForbidden();
    }

    public function test_student_only_sees_own_history_and_no_teacher_tab(): void
    {
        [$studentUser, $student] = $this->createApiStudent();
        [, $other] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $classA = $this->createApiClass($year);
        $classB = $this->createApiClass($year);
        $own = $this->createApiAttendance($student, $classA);
        $otherRow = $this->createApiAttendance($other, $classB);

        $this->actingAs($studentUser, 'web');
        $this->inertiaGet('/attendance')->assertOk()
            ->assertJsonPath('component', 'Attendance/Index')
            ->assertJsonPath('props.attendance.records.total', 1)
            ->assertJsonPath('props.attendance.records.data.0.uuid', $own->uuid)
            ->assertJsonPath('props.attendance.can.check_in', true)
            ->assertDontSee($otherRow->uuid);
        $this->get('/attendance?type=teachers')->assertForbidden();
    }

    public function test_parent_only_sees_linked_child_and_has_no_checkin_action(): void
    {
        [$parent, $guardian] = $this->createApiParent();
        [, $child] = $this->createApiStudent();
        [, $unrelated] = $this->createApiStudent();
        $this->linkApiParentToStudent($guardian, $child);
        $year = $this->createApiAcademicYear();
        $class = $this->createApiClass($year);
        $visible = $this->createApiAttendance($child, $class);
        $hidden = $this->createApiAttendance($unrelated, $class);

        $this->actingAs($parent, 'web');
        $this->inertiaGet('/attendance')->assertOk()
            ->assertJsonPath('props.attendance.records.total', 1)
            ->assertJsonPath('props.attendance.records.data.0.uuid', $visible->uuid)
            ->assertJsonPath('props.attendance.can.check_in', false)
            ->assertDontSee($hidden->uuid);
        $this->post('/attendance/students/check-in', $this->gps())->assertForbidden();
    }

    public function test_teacher_can_switch_between_homeroom_students_and_own_attendance(): void
    {
        [$teacherUser, $teacher] = $this->createApiTeacher();
        [, $otherTeacher] = $this->createApiTeacher();
        [, $studentA] = $this->createApiStudent();
        [, $studentB] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $classA = $this->createApiClass($year, $teacher);
        $classB = $this->createApiClass($year, $otherTeacher);
        $ownStudent = $this->createApiAttendance($studentA, $classA);
        $otherStudent = $this->createApiAttendance($studentB, $classB);
        $ownTeacher = $this->teacherRecord($teacher);
        $otherTeacherRow = $this->teacherRecord($otherTeacher);

        $this->actingAs($teacherUser, 'web');
        $this->inertiaGet('/attendance?type=students')->assertOk()
            ->assertJsonPath('props.attendance.records.total', 1)
            ->assertJsonPath('props.attendance.records.data.0.uuid', $ownStudent->uuid)
            ->assertDontSee($otherStudent->uuid);
        $this->inertiaGet('/attendance?type=teachers')->assertOk()
            ->assertJsonPath('props.attendance.records.total', 1)
            ->assertJsonPath('props.attendance.records.data.0.uuid', $ownTeacher->uuid)
            ->assertDontSee($otherTeacherRow->uuid);
    }

    public function test_teacher_cannot_escape_homeroom_scope_via_class_filter(): void
    {
        [$user, $teacher] = $this->createApiTeacher();
        [, $otherTeacher] = $this->createApiTeacher();
        [, $student] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $this->createApiClass($year, $teacher);
        $foreignClass = $this->createApiClass($year, $otherTeacher);
        $this->createApiAttendance($student, $foreignClass);

        $this->actingAs($user, 'web');
        $this->inertiaGet('/attendance?type=students&class_uuid=' . $foreignClass->uuid)
            ->assertOk()->assertJsonPath('props.attendance.records.total', 0);
    }

    public function test_admin_can_monitor_both_types_but_cannot_check_in_without_profile(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        [, $teacher] = $this->createApiTeacher();
        $year = $this->createApiAcademicYear();
        $class = $this->createApiClass($year);
        $this->createApiAttendance($student, $class);
        $this->teacherRecord($teacher);

        $this->actingAs($admin, 'web');
        $this->inertiaGet('/attendance?type=students')->assertOk()
            ->assertJsonPath('props.attendance.records.total', 1)
            ->assertJsonPath('props.attendance.can.check_in', false);
        $this->inertiaGet('/attendance?type=teachers')->assertOk()
            ->assertJsonPath('props.attendance.records.total', 1)
            ->assertJsonPath('props.attendance.can.check_in', false);
        $this->post('/attendance/students/check-in', $this->gps())->assertForbidden();
    }

    public function test_validation_errors_are_redirected_for_inertia_form(): void
    {
        [$user] = $this->createApiStudent();
        $this->actingAs($user, 'web')->from('/attendance?type=students')
            ->post('/attendance/students/check-in', [
                'latitude' => 100,
                'longitude' => 190,
                'accuracy' => -1,
            ])->assertRedirect('/attendance?type=students')
            ->assertSessionHasErrors(['latitude', 'longitude', 'accuracy']);
    }

    public function test_student_self_checkin_duplicate_and_checkout_use_existing_actions(): void
    {
        [$user, $student] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $class = $this->createApiClass($year);
        $this->enrollApiStudent($student, $class);
        $location = $this->createApiSchoolLocation();
        $this->createApiStudentSchedule($year, $location);

        $this->actingAs($user, 'web');
        $this->post('/attendance/students/check-in', [
            ...$this->gps(),
            'student_id' => 999999,
        ])->assertRedirect('/attendance?type=students')
            ->assertSessionHas('success');
        $this->assertDatabaseHas('student_attendances', ['student_id' => $student->id]);
        $this->assertDatabaseCount('student_attendances', 1);
        $this->inertiaGet('/attendance?type=students')->assertJsonPath('props.attendance.own_today.check_out_at', null);

        $this->from('/attendance?type=students')
            ->post('/attendance/students/check-in', $this->gps())
            ->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('student_attendances', 1);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-14 14:30:00', 'Asia/Jakarta'));
        $this->post('/attendance/students/check-out', $this->gps())
            ->assertRedirect('/attendance?type=students')
            ->assertSessionHas('success');
        $this->assertDatabaseCount('student_attendances', 1);
        $this->assertNotNull($student->attendances()->first()->check_out_at);
    }

    public function test_teacher_check_in_and_out_reuse_teacher_attendance_action(): void
    {
        [$user, $teacher] = $this->createApiTeacher();
        $year = $this->createApiAcademicYear();
        $location = $this->createApiSchoolLocation();
        $this->createApiStudentSchedule($year, $location)->update(['attendance_type' => 'TEACHER']);

        $this->actingAs($user, 'web');
        $this->post('/attendance/teachers/check-in', $this->gps())
            ->assertRedirect('/attendance?type=teachers')->assertSessionHas('success');
        $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $teacher->id]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-14 14:30:00', 'Asia/Jakarta'));
        $this->post('/attendance/teachers/check-out', $this->gps())
            ->assertRedirect('/attendance?type=teachers')->assertSessionHas('success');
        $this->assertDatabaseCount('teacher_attendances', 1);
        $this->assertNotNull(TeacherAttendance::query()->first()->check_out_at);
    }

    public function test_student_outside_geofence_and_bad_accuracy_show_server_errors(): void
    {
        [$user, $student] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $class = $this->createApiClass($year);
        $this->enrollApiStudent($student, $class);
        $location = $this->createApiSchoolLocation();
        $this->createApiStudentSchedule($year, $location);
        $this->actingAs($user, 'web')->from('/attendance?type=students');

        $this->post('/attendance/students/check-in', [
            'latitude' => -6.25,
            'longitude' => 106.8,
            'accuracy' => 10,
        ])->assertSessionHasErrors('location');
        $this->post('/attendance/students/check-in', [
            ...$this->gps(),
            'accuracy' => 500,
        ])->assertSessionHasErrors('accuracy');
        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_approved_teacher_leave_is_rejected_by_existing_action(): void
    {
        [$user, $teacher] = $this->createApiTeacher();
        $year = $this->createApiAcademicYear();
        $location = $this->createApiSchoolLocation();
        $this->createApiStudentSchedule($year, $location)->update(['attendance_type' => 'TEACHER']);
        TeacherLeave::query()->create([
            'teacher_id' => $teacher->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
            'leave_type' => TeacherLeaveType::Sick,
            'reason' => 'Izin sakit disetujui',
            'attachment_path' => null,
            'status' => TeacherLeaveStatus::Approved,
        ]);
        $this->actingAs($user, 'web')->from('/attendance?type=teachers')
            ->post('/attendance/teachers/check-in', $this->gps())
            ->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('teacher_attendances', 0);
    }

    public function test_invalid_filter_is_rejected_and_no_gps_coordinates_are_serialized(): void
    {
        [$user, $student] = $this->createApiStudent();
        $class = $this->createApiClass($this->createApiAcademicYear());
        $this->createApiAttendance($student, $class);
        $this->actingAs($user, 'web');
        $this->get('/attendance?status=HACKED')->assertSessionHasErrors('status');
        $this->inertiaGet('/attendance')->assertOk()
            ->assertDontSee('check_in_latitude')->assertDontSee('check_in_longitude')
            ->assertDontSee('distance_from_school');
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Attendance\AttendanceSource;
use App\Enums\Attendance\AttendanceStatus;
use App\Enums\Finance\PaymentMethod;
use App\Enums\Finance\SppBillStatus;
use App\Enums\Finance\SppPaymentStatus;
use App\Models\Attendance\StudentAttendance;
use App\Models\Attendance\TeacherAttendance;
use App\Models\Finance\SavingTransaction;
use App\Models\Finance\SppPayment;
use Carbon\CarbonImmutable;

class DashboardReportingHttpTest extends HttpApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-09-15 10:00:00',
                'Asia/Jakarta'
            )
        );
    }

    public function test_guest_gets_401_for_dashboard(): void
    {
        $this->getJson(
            route(
                'api.v1.dashboard.overview'
            )
        )->assertUnauthorized();
    }

    public function test_student_dashboard_only_uses_own_finance_and_attendance_scope(): void
    {
        [$studentUser, $student] =
            $this->createApiStudent();

        [, $otherStudent] =
            $this->createApiStudent();

        $year =
            $this->createApiAcademicYear();

        $class =
            $this->createApiClass($year);

        $otherClass =
            $this->createApiClass($year);

        $this->enrollApiStudent(
            $student,
            $class
        );

        $this->enrollApiStudent(
            $otherStudent,
            $otherClass
        );

        $this->createStudentAttendance(
            $student,
            $class,
            '2026-09-15',
            AttendanceStatus::Late,
            20
        );

        $this->createStudentAttendance(
            $otherStudent,
            $otherClass,
            '2026-09-15',
            AttendanceStatus::Present
        );

        $this->createApiSavingAccount(
            $student,
            '100000.00'
        );

        $this->createApiSavingAccount(
            $otherStudent,
            '900000.00'
        );

        $this->createApiSppBill(
            $student,
            $year,
            '500000.00'
        );

        $this->createApiSppBill(
            $otherStudent,
            $year,
            '900000.00'
        );

        $this->actingAs($studentUser)
            ->getJson(
                route(
                    'api.v1.dashboard.overview',
                    [
                        'date' =>
                            '2026-09-15',
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.student_attendance.total',
                1
            )
            ->assertJsonPath(
                'data.student_attendance.late',
                1
            )
            ->assertJsonPath(
                'data.savings.accounts',
                1
            )
            ->assertJsonPath(
                'data.savings.total_balance',
                '100000.00'
            )
            ->assertJsonPath(
                'data.spp.open_bills',
                1
            )
            ->assertJsonPath(
                'data.spp.outstanding_amount',
                '500000.00'
            )
            ->assertJsonMissingPath(
                'data.teacher_attendance'
            );
    }

    public function test_teacher_dashboard_scopes_student_attendance_to_homeroom_class(): void
    {
        [$teacherUser, $teacher] =
            $this->createApiTeacher();

        [, $otherTeacher] =
            $this->createApiTeacher();

        [, $studentA] =
            $this->createApiStudent();

        [, $studentB] =
            $this->createApiStudent();

        $year =
            $this->createApiAcademicYear();

        $classA =
            $this->createApiClass(
                $year,
                $teacher
            );

        $classB =
            $this->createApiClass(
                $year,
                $otherTeacher
            );

        $this->enrollApiStudent(
            $studentA,
            $classA
        );

        $this->enrollApiStudent(
            $studentB,
            $classB
        );

        $this->createStudentAttendance(
            $studentA,
            $classA,
            '2026-09-15',
            AttendanceStatus::Present
        );

        $this->createStudentAttendance(
            $studentB,
            $classB,
            '2026-09-15',
            AttendanceStatus::Late,
            15
        );

        $this->createTeacherAttendance(
            $teacher,
            '2026-09-15',
            AttendanceStatus::Present
        );

        $this->createTeacherAttendance(
            $otherTeacher,
            '2026-09-15',
            AttendanceStatus::Late,
            10
        );

        $this->actingAs($teacherUser)
            ->getJson(
                route(
                    'api.v1.dashboard.overview',
                    [
                        'date' =>
                            '2026-09-15',
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.student_attendance.total',
                1
            )
            ->assertJsonPath(
                'data.student_attendance.present',
                1
            )
            ->assertJsonPath(
                'data.teacher_attendance.total',
                1
            )
            ->assertJsonPath(
                'data.teacher_attendance.present',
                1
            );
    }

    public function test_student_cannot_open_savings_report(): void
    {
        [$studentUser] =
            $this->createApiStudent();

        $this->actingAs($studentUser)
            ->getJson(
                route(
                    'api.v1.reports.savings',
                    [
                        'from' =>
                            '2026-09-01',
                        'to' =>
                            '2026-09-30',
                    ]
                )
            )
            ->assertForbidden();
    }

    public function test_teacher_student_attendance_report_is_scoped_to_homeroom_class(): void
    {
        [$teacherUser, $teacher] =
            $this->createApiTeacher();

        [, $otherTeacher] =
            $this->createApiTeacher();

        [, $studentA] =
            $this->createApiStudent();

        [, $studentB] =
            $this->createApiStudent();

        $year =
            $this->createApiAcademicYear();

        $classA =
            $this->createApiClass(
                $year,
                $teacher
            );

        $classB =
            $this->createApiClass(
                $year,
                $otherTeacher
            );

        $this->createStudentAttendance(
            $studentA,
            $classA,
            '2026-09-15',
            AttendanceStatus::Present
        );

        $this->createStudentAttendance(
            $studentB,
            $classB,
            '2026-09-15',
            AttendanceStatus::Late,
            20
        );

        $this->actingAs($teacherUser)
            ->getJson(
                route(
                    'api.v1.reports.attendance.students',
                    [
                        'from' =>
                            '2026-09-01',
                        'to' =>
                            '2026-09-30',
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_records',
                1
            )
            ->assertJsonPath(
                'data.summary.present',
                1
            )
            ->assertJsonPath(
                'data.summary.late',
                0
            );
    }

    public function test_teacher_attendance_report_is_scoped_to_own_record_for_teacher(): void
    {
        [$teacherUser, $teacher] =
            $this->createApiTeacher();

        [, $otherTeacher] =
            $this->createApiTeacher();

        $this->createTeacherAttendance(
            $teacher,
            '2026-09-15',
            AttendanceStatus::Late,
            20
        );

        $this->createTeacherAttendance(
            $otherTeacher,
            '2026-09-15',
            AttendanceStatus::Present
        );

        $this->actingAs($teacherUser)
            ->getJson(
                route(
                    'api.v1.reports.attendance.teachers',
                    [
                        'from' =>
                            '2026-09-01',
                        'to' =>
                            '2026-09-30',
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_records',
                1
            )
            ->assertJsonPath(
                'data.summary.late',
                1
            )
            ->assertJsonPath(
                'data.summary.late_minutes.total',
                20
            );
    }

    public function test_principal_savings_report_aggregates_posted_transactions(): void
    {
        $principal =
            $this->createApiPrincipal();

        $admin =
            $this->createApiAdmin();

        [, $student] =
            $this->createApiStudent();

        $account =
            $this->createApiSavingAccount(
                $student,
                '100000.00'
            );

        $this->createApiSavingTransaction(
            $admin,
            $account,
            '50000.00'
        );

        SavingTransaction::query()
            ->create([
                'transaction_number' =>
                    'TRX-WITHDRAW-REPORT',
                'saving_account_id' =>
                    $account->id,
                'created_by' =>
                    $admin->id,
                'reference_transaction_id' =>
                    null,
                'transaction_type' =>
                    'WITHDRAWAL',
                'amount' =>
                    '20000.00',
                'balance_before' =>
                    '150000.00',
                'balance_after' =>
                    '130000.00',
                'description' =>
                    'Reporting fixture',
                'status' =>
                    'POSTED',
                'transaction_date' =>
                    '2026-09-15 09:00:00',
            ]);

        $account->update([
            'current_balance' =>
                '130000.00',
        ]);

        $this->actingAs($principal)
            ->getJson(
                route(
                    'api.v1.reports.savings',
                    [
                        'from' =>
                            '2026-09-01',
                        'to' =>
                            '2026-09-30',
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.snapshot.accounts',
                1
            )
            ->assertJsonPath(
                'data.snapshot.total_balance',
                '130000.00'
            )
            ->assertJsonPath(
                'data.transactions.deposit.amount',
                '50000.00'
            )
            ->assertJsonPath(
                'data.transactions.withdrawal.amount',
                '20000.00'
            );
    }

    public function test_principal_spp_report_separates_bill_cohort_and_collection_flow(): void
    {
        $principal =
            $this->createApiPrincipal();

        $admin =
            $this->createApiAdmin();

        [, $student] =
            $this->createApiStudent();

        $year =
            $this->createApiAcademicYear();

        $bill =
            $this->createApiSppBill(
                $student,
                $year,
                '500000.00'
            );

        $bill->update([
            'paid_amount' =>
                '200000.00',
            'status' =>
                SppBillStatus::Partial,
        ]);

        SppPayment::query()->create([
            'payment_number' =>
                'PAY-REPORT-001',
            'receipt_number' =>
                'RCT-REPORT-001',
            'spp_bill_id' =>
                $bill->id,
            'created_by' =>
                $admin->id,
            'amount' =>
                '200000.00',
            'payment_method' =>
                PaymentMethod::Cash,
            'reference_number' =>
                null,
            'payment_date' =>
                '2026-09-15 08:00:00',
            'status' =>
                SppPaymentStatus::Posted,
            'notes' =>
                'Reporting fixture',
        ]);

        $this->actingAs($principal)
            ->getJson(
                route(
                    'api.v1.reports.spp',
                    [
                        'from' =>
                            '2026-09-01',
                        'to' =>
                            '2026-09-30',
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.bills.billed_amount',
                '500000.00'
            )
            ->assertJsonPath(
                'data.bills.paid_amount',
                '200000.00'
            )
            ->assertJsonPath(
                'data.bills.outstanding_amount',
                '300000.00'
            )
            ->assertJsonPath(
                'data.bills.collection_ratio_percent',
                40
            )
            ->assertJsonPath(
                'data.collections.amount',
                '200000.00'
            );
    }

    public function test_report_range_over_366_days_is_rejected(): void
    {
        $principal =
            $this->createApiPrincipal();

        $this->actingAs($principal)
            ->getJson(
                route(
                    'api.v1.reports.spp',
                    [
                        'from' =>
                            '2025-01-01',
                        'to' =>
                            '2026-09-15',
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'to'
            );
    }

    private function createStudentAttendance(
        $student,
        $class,
        string $date,
        AttendanceStatus $status,
        int $lateMinutes = 0
    ): StudentAttendance {
        return StudentAttendance::query()
            ->create([
                'student_id' =>
                    $student->id,
                'class_id' =>
                    $class->id,
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

    private function createTeacherAttendance(
        $teacher,
        string $date,
        AttendanceStatus $status,
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
}

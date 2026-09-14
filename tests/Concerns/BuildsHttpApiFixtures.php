<?php

namespace Tests\Concerns;

use App\Enums\Academic\EnrollmentStatus;
use App\Enums\Academic\ParentRelationship;
use App\Enums\Academic\SchoolClassStatus;
use App\Enums\Academic\StudentStatus;
use App\Enums\Academic\TeacherEmploymentStatus;
use App\Enums\Academic\TeacherStatus;
use App\Enums\Attendance\AttendanceSource;
use App\Enums\Attendance\AttendanceStatus;
use App\Enums\Attendance\AttendanceType;
use App\Enums\Common\Gender;
use App\Enums\Finance\SavingAccountStatus;
use App\Enums\Finance\SavingTransactionStatus;
use App\Enums\Finance\SavingTransactionType;
use App\Enums\Finance\SppBillStatus;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Guardian;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Student;
use App\Models\Academic\StudentClassEnrollment;
use App\Models\Academic\Teacher;
use App\Models\Attendance\AttendanceSchedule;
use App\Models\Attendance\SchoolLocation;
use App\Models\Attendance\StudentAttendance;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SavingTransaction;
use App\Models\Finance\SppBill;
use Illuminate\Support\Str;

trait BuildsHttpApiFixtures
{
    private int $httpFixtureSequence = 0;

    protected function httpToken(string $prefix): string
    {
        $this->httpFixtureSequence++;

        return $prefix
            . '-' . $this->httpFixtureSequence
            . '-' . Str::lower(Str::random(6));
    }

    protected function createApiUserWithRole(string $roleName): User
    {
        $token = $this->httpToken($roleName);

        $user = User::query()->create([
            'name' => ucfirst($roleName) . ' HTTP Test',
            'username' => $token,
            'email' => $token . '@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $role = Role::query()
            ->where('name', $roleName)
            ->firstOrFail();

        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    /**
     * @return array{0: User, 1: Student}
     */
    protected function createApiStudent(): array
    {
        $user = $this->createApiUserWithRole('student');
        $token = $this->httpToken('student');

        $student = Student::query()->create([
            'user_id' => $user->id,
            'nis' => 'NIS-' . $token,
            'nisn' => null,
            'gender' => Gender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => '2013-01-01',
            'address' => 'HTTP Test Address',
            'admission_date' => '2026-07-01',
            'graduation_date' => null,
            'status' => StudentStatus::Active,
        ]);

        return [$user->fresh(), $student];
    }

    /**
     * @return array{0: User, 1: Teacher}
     */
    protected function createApiTeacher(): array
    {
        $user = $this->createApiUserWithRole('teacher');
        $token = $this->httpToken('teacher');

        $teacher = Teacher::query()->create([
            'user_id' => $user->id,
            'nip' => 'NIP-' . $token,
            'employee_number' => 'EMP-' . $token,
            'gender' => Gender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => '1990-01-01',
            'address' => 'HTTP Test Address',
            'employment_status' => TeacherEmploymentStatus::Permanent,
            'join_date' => '2020-07-01',
            'status' => TeacherStatus::Active,
        ]);

        return [$user->fresh(), $teacher];
    }

    /**
     * @return array{0: User, 1: Guardian}
     */
    protected function createApiParent(): array
    {
        $user = $this->createApiUserWithRole('parent');

        $guardian = Guardian::query()->create([
            'user_id' => $user->id,
            'occupation' => 'HTTP Test Occupation',
            'address' => 'HTTP Test Address',
        ]);

        return [$user->fresh(), $guardian];
    }

    protected function createApiAdmin(): User
    {
        return $this->createApiUserWithRole('admin');
    }

    protected function createApiPrincipal(): User
    {
        return $this->createApiUserWithRole('principal');
    }

    protected function linkApiParentToStudent(
        Guardian $guardian,
        Student $student
    ): void {
        $guardian->students()->attach($student->id, [
            'relationship' => ParentRelationship::Father->value,
            'is_primary_contact' => true,
            'receive_notification' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createApiAcademicYear(): AcademicYear
    {
        $token = $this->httpToken('year');

        return AcademicYear::query()->create([
            'name' => '2026/2027-' . $token,
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
    }

    protected function createApiClass(
        AcademicYear $academicYear,
        ?Teacher $homeroomTeacher = null
    ): SchoolClass {
        $token = $this->httpToken('class');

        return SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'homeroom_teacher_id' => $homeroomTeacher?->id,
            'code' => 'CLS-' . $token,
            'name' => 'Class ' . $token,
            'grade_level' => '7',
            'major' => null,
            'status' => SchoolClassStatus::Active,
        ]);
    }

    protected function enrollApiStudent(
        Student $student,
        SchoolClass $schoolClass
    ): StudentClassEnrollment {
        return StudentClassEnrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $schoolClass->id,
            'joined_at' => '2026-07-01',
            'left_at' => null,
            'status' => EnrollmentStatus::Active,
        ]);
    }

    protected function createApiSchoolLocation(): SchoolLocation
    {
        $token = $this->httpToken('location');

        return SchoolLocation::query()->create([
            'code' => 'LOC-' . $token,
            'name' => 'HTTP Test Campus',
            'address' => 'Jakarta',
            'latitude' => '-6.2000000',
            'longitude' => '106.8000000',
            'radius_meters' => 150,
            'is_active' => true,
        ]);
    }

    protected function createApiStudentSchedule(
        AcademicYear $academicYear,
        SchoolLocation $location
    ): AttendanceSchedule {
        $token = $this->httpToken('schedule');

        return AttendanceSchedule::query()->create([
            'name' => 'HTTP Student Schedule ' . $token,
            'attendance_type' => AttendanceType::Student,
            'day_of_week' => 1,
            'check_in_start' => '06:00:00',
            'check_in_deadline' => '07:00:00',
            'check_in_end' => '09:00:00',
            'check_out_start' => '14:00:00',
            'check_out_end' => '17:00:00',
            'late_tolerance_minutes' => 5,
            'school_location_id' => $location->id,
            'academic_year_id' => $academicYear->id,
            'is_active' => true,
            'effective_from' => '2026-07-01',
            'effective_until' => '2027-06-30',
        ]);
    }

    protected function createApiAttendance(
        Student $student,
        SchoolClass $schoolClass,
        string $date = '2026-09-14'
    ): StudentAttendance {
        return StudentAttendance::query()->create([
            'student_id' => $student->id,
            'class_id' => $schoolClass->id,
            'attendance_schedule_id' => null,
            'school_location_id' => null,
            'attendance_date' => $date,
            'check_in_at' => $date . ' 06:55:00',
            'check_out_at' => null,
            'check_in_latitude' => '-6.2000000',
            'check_in_longitude' => '106.8000000',
            'location_accuracy' => '10.00',
            'distance_from_school' => '0.00',
            'status' => AttendanceStatus::Present,
            'late_minutes' => 0,
            'source' => AttendanceSource::Geolocation,
            'notes' => null,
        ]);
    }

    protected function createApiSavingAccount(
        Student $student,
        string $balance = '100000.00'
    ): SavingAccount {
        $token = $this->httpToken('saving');

        return SavingAccount::query()->create([
            'student_id' => $student->id,
            'account_number' => 'SAV-' . $token,
            'current_balance' => $balance,
            'status' => SavingAccountStatus::Active,
            'opened_at' => now(),
            'closed_at' => null,
        ]);
    }

    protected function createApiSavingTransaction(
        User $actor,
        SavingAccount $account,
        string $amount = '50000.00'
    ): SavingTransaction {
        $token = $this->httpToken('trx');
        $before = $account->current_balance;
        $after = bcadd($before, $amount, 2);

        $transaction = SavingTransaction::query()->create([
            'transaction_number' => 'TRX-' . $token,
            'saving_account_id' => $account->id,
            'created_by' => $actor->id,
            'reference_transaction_id' => null,
            'transaction_type' => SavingTransactionType::Deposit,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'description' => 'HTTP fixture',
            'status' => SavingTransactionStatus::Posted,
            'transaction_date' => now(),
        ]);

        $account->update([
            'current_balance' => $after,
        ]);

        return $transaction;
    }

    protected function createApiSppBill(
        Student $student,
        AcademicYear $academicYear,
        string $amount = '500000.00',
        int $month = 9
    ): SppBill {
        $token = $this->httpToken('bill');

        return SppBill::query()->create([
            'bill_number' => 'BILL-' . $token,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'billing_month' => $month,
            'billing_year' => 2026,
            'amount' => $amount,
            'paid_amount' => '0.00',
            'due_date' => '2026-09-10',
            'status' => SppBillStatus::Overdue,
            'notes' => null,
        ]);
    }
}

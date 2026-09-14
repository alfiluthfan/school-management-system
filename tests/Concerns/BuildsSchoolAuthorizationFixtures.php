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
use App\Enums\Common\Gender;
use App\Enums\Finance\SavingAccountStatus;
use App\Enums\Finance\SppBillStatus;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Guardian;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Student;
use App\Models\Academic\StudentClassEnrollment;
use App\Models\Academic\Teacher;
use App\Models\Attendance\StudentAttendance;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SppBill;
use Illuminate\Support\Str;

trait BuildsSchoolAuthorizationFixtures
{
    private int $sequence = 0;

    protected function nextToken(string $prefix): string
    {
        $this->sequence++;
        return $prefix . '-' . $this->sequence . '-' . Str::lower(Str::random(6));
    }

    protected function createUserWithRole(string $roleName): User
    {
        $token = $this->nextToken($roleName);
        $user = User::query()->create([
            'name' => ucfirst($roleName) . ' Test ' . $token,
            'username' => $token,
            'email' => $token . '@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role->id);
        return $user->fresh();
    }

    protected function createStudentUser(): array
    {
        $user = $this->createUserWithRole('student');
        $token = $this->nextToken('student');
        $student = Student::query()->create([
            'user_id' => $user->id,
            'nis' => 'NIS-' . $token,
            'nisn' => null,
            'gender' => Gender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => now()->subYears(13)->toDateString(),
            'address' => 'Test Address',
            'admission_date' => now()->subYear()->toDateString(),
            'status' => StudentStatus::Active,
        ]);
        return [$user->fresh(), $student];
    }

    protected function createTeacherUser(): array
    {
        $user = $this->createUserWithRole('teacher');
        $token = $this->nextToken('teacher');
        $teacher = Teacher::query()->create([
            'user_id' => $user->id,
            'nip' => 'NIP-' . $token,
            'employee_number' => 'EMP-' . $token,
            'gender' => Gender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => now()->subYears(30)->toDateString(),
            'address' => 'Test Address',
            'employment_status' => TeacherEmploymentStatus::Permanent,
            'join_date' => now()->subYears(3)->toDateString(),
            'status' => TeacherStatus::Active,
        ]);
        return [$user->fresh(), $teacher];
    }

    protected function createGuardianUser(): array
    {
        $user = $this->createUserWithRole('parent');
        $guardian = Guardian::query()->create([
            'user_id' => $user->id,
            'occupation' => 'Test Occupation',
            'address' => 'Test Address',
        ]);
        return [$user->fresh(), $guardian];
    }

    protected function createPrincipalUser(): User
    {
        return $this->createUserWithRole('principal');
    }
    protected function createAdminUser(): User
    {
        return $this->createUserWithRole('admin');
    }

    protected function linkGuardianToStudent(Guardian $guardian, Student $student, bool $primary = true): void
    {
        $guardian->students()->attach($student->id, [
            'relationship' => ParentRelationship::Father,
            'is_primary_contact' => $primary,
            'receive_notification' => true,
        ]);
    }

    protected function createAcademicYear(bool $active = true): AcademicYear
    {
        $token = $this->nextToken('year');
        return AcademicYear::query()->create([
            'name' => '2026/2027-' . $token,
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => $active,
        ]);
    }

    protected function createSchoolClass(AcademicYear $academicYear, ?Teacher $homeroomTeacher = null): SchoolClass
    {
        $token = $this->nextToken('class');
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

    protected function enrollStudent(Student $student, SchoolClass $schoolClass): StudentClassEnrollment
    {
        return StudentClassEnrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $schoolClass->id,
            'joined_at' => '2026-07-01',
            'left_at' => null,
            'status' => EnrollmentStatus::Active,
        ]);
    }

    protected function createStudentAttendance(Student $student, SchoolClass $schoolClass, string $date = '2026-09-14'): StudentAttendance
    {
        return StudentAttendance::query()->create([
            'student_id' => $student->id,
            'class_id' => $schoolClass->id,
            'attendance_schedule_id' => null,
            'school_location_id' => null,
            'attendance_date' => $date,
            'check_in_at' => $date . ' 06:55:00',
            'check_out_at' => null,
            'status' => AttendanceStatus::Present,
            'late_minutes' => 0,
            'source' => AttendanceSource::Geolocation,
        ]);
    }

    protected function createSavingAccount(Student $student, string $balance = '100000.00'): SavingAccount
    {
        $token = $this->nextToken('saving');
        return SavingAccount::query()->create([
            'student_id' => $student->id,
            'account_number' => 'SAV-' . $token,
            'current_balance' => $balance,
            'status' => SavingAccountStatus::Active,
            'opened_at' => now(),
        ]);
    }

    protected function createSppBill(Student $student, AcademicYear $academicYear, int $month = 9, int $year = 2026): SppBill
    {
        $token = $this->nextToken('bill');
        return SppBill::query()->create([
            'bill_number' => 'BILL-' . $token,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'billing_month' => $month,
            'billing_year' => $year,
            'amount' => '500000.00',
            'paid_amount' => '0.00',
            'due_date' => '2026-09-10',
            'status' => SppBillStatus::Overdue,
            'notes' => null,
        ]);
    }
}

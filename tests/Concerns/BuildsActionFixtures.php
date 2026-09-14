<?php

namespace Tests\Concerns;

use App\Enums\Academic\EnrollmentStatus;
use App\Enums\Academic\SchoolClassStatus;
use App\Enums\Academic\StudentStatus;
use App\Enums\Attendance\AttendanceType;
use App\Enums\Common\Gender;
use App\Enums\Finance\SavingAccountStatus;
use App\Enums\Finance\SppBillStatus;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Student;
use App\Models\Academic\StudentClassEnrollment;
use App\Models\Attendance\AttendanceSchedule;
use App\Models\Attendance\SchoolLocation;
use App\Models\Auth\User;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SppBill;
use Illuminate\Support\Str;

trait BuildsActionFixtures
{
    private int $fixtureSequence = 0;

    protected function fixtureToken(string $prefix): string
    {
        $this->fixtureSequence++;

        return $prefix . '-' . $this->fixtureSequence . '-' . Str::lower(Str::random(6));
    }

    protected function createActor(string $prefix = 'actor'): User
    {
        $token = $this->fixtureToken($prefix);

        return User::query()->create([
            'name' => 'Test Actor ' . $token,
            'username' => $token,
            'email' => $token . '@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    /**
     * @return array{0: User, 1: Student}
     */
    protected function createStudentProfile(): array
    {
        $user = $this->createActor('student');
        $token = $this->fixtureToken('student');

        $student = Student::query()->create([
            'user_id' => $user->id,
            'nis' => 'NIS-' . $token,
            'nisn' => null,
            'gender' => Gender::Male,
            'birth_place' => 'Jakarta',
            'birth_date' => '2013-01-01',
            'address' => 'Test Address',
            'admission_date' => '2026-07-01',
            'graduation_date' => null,
            'status' => StudentStatus::Active,
        ]);

        return [$user, $student];
    }

    protected function createAcademicYear(): AcademicYear
    {
        return AcademicYear::query()->create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
    }

    protected function createSchoolClass(
        AcademicYear $academicYear
    ): SchoolClass {
        return SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'homeroom_teacher_id' => null,
            'code' => 'VII-A',
            'name' => 'Kelas VII-A',
            'grade_level' => '7',
            'major' => null,
            'status' => SchoolClassStatus::Active,
        ]);
    }

    protected function enroll(
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

    protected function createSchoolLocation(): SchoolLocation
    {
        return SchoolLocation::query()->create([
            'code' => 'MAIN',
            'name' => 'Kampus Utama',
            'address' => 'Jakarta',
            'latitude' => '-6.2000000',
            'longitude' => '106.8000000',
            'radius_meters' => 150,
            'is_active' => true,
        ]);
    }

    protected function createStudentSchedule(
        AcademicYear $academicYear,
        SchoolLocation $location
    ): AttendanceSchedule {
        return AttendanceSchedule::query()->create([
            'name' => 'Jadwal Siswa Senin',
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

    /**
     * @return array{
     *   actor: User,
     *   student: Student,
     *   academicYear: AcademicYear,
     *   schoolClass: SchoolClass,
     *   location: SchoolLocation,
     *   schedule: AttendanceSchedule
     * }
     */
    protected function createAttendanceScenario(): array
    {
        [$actor, $student] = $this->createStudentProfile();
        $academicYear = $this->createAcademicYear();
        $schoolClass = $this->createSchoolClass($academicYear);
        $this->enroll($student, $schoolClass);
        $location = $this->createSchoolLocation();
        $schedule = $this->createStudentSchedule(
            $academicYear,
            $location
        );

        return compact(
            'actor',
            'student',
            'academicYear',
            'schoolClass',
            'location',
            'schedule'
        );
    }

    protected function createSavingAccount(
        Student $student,
        string $balance = '100000.00'
    ): SavingAccount {
        $token = $this->fixtureToken('saving');

        return SavingAccount::query()->create([
            'student_id' => $student->id,
            'account_number' => 'SAV-' . $token,
            'current_balance' => $balance,
            'status' => SavingAccountStatus::Active,
            'opened_at' => now(),
            'closed_at' => null,
        ]);
    }

    protected function createSppBill(
        Student $student,
        AcademicYear $academicYear,
        string $amount = '500000.00'
    ): SppBill {
        $token = $this->fixtureToken('bill');

        return SppBill::query()->create([
            'bill_number' => 'BILL-' . $token,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'billing_month' => 9,
            'billing_year' => 2026,
            'amount' => $amount,
            'paid_amount' => '0.00',
            'due_date' => '2026-09-10',
            'status' => SppBillStatus::Overdue,
            'notes' => null,
        ]);
    }
}

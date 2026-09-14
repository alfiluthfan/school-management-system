<?php

namespace App\Actions\Attendance;

use App\Enums\Academic\EnrollmentStatus;
use App\Enums\Attendance\AttendanceSource;
use App\Enums\Attendance\AttendanceStatus;
use App\Enums\Attendance\AttendanceType;
use App\Events\Attendance\StudentLateDetected;
use App\Models\Academic\Student;
use App\Models\Attendance\AttendanceSchedule;
use App\Models\Attendance\StudentAttendance;
use App\Models\Auth\User;
use App\Services\Attendance\AttendanceScheduleResolver;
use App\Services\Attendance\AttendanceStatusCalculator;
use App\Services\Attendance\GeofenceService;
use App\Services\System\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CheckInStudentAction
{
    public function __construct(
        private readonly AttendanceScheduleResolver $scheduleResolver,
        private readonly AttendanceStatusCalculator $statusCalculator,
        private readonly GeofenceService $geofence,
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function execute(
        User $actor,
        Student $student,
        float $latitude,
        float $longitude,
        ?float $accuracy = null,
        ?CarbonImmutable $occurredAt = null
    ): StudentAttendance {
        $occurredAt ??= CarbonImmutable::now(config('app.timezone'));

        $this->assertCoordinates($latitude, $longitude);
        $this->assertAccuracy($accuracy);

        $enrollment = $student->enrollments()
            ->where('status', EnrollmentStatus::Active->value)
            ->whereHas(
                'schoolClass.academicYear',
                fn ($query) => $query->where('is_active', true)
            )
            ->with('schoolClass')
            ->first();

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'student' => 'Siswa tidak memiliki enrollment aktif.',
            ]);
        }

        $schedules = $this->scheduleResolver->candidates(
            AttendanceType::Student,
            $occurredAt
        );

        [$schedule, $distance] = $this->resolveLocation(
            $schedules,
            $latitude,
            $longitude
        );

        $status = $this->statusCalculator->forCheckIn(
            $schedule,
            $occurredAt
        );

        return DB::transaction(function () use (
            $actor,
            $student,
            $enrollment,
            $schedule,
            $distance,
            $status,
            $latitude,
            $longitude,
            $accuracy,
            $occurredAt
        ): StudentAttendance {
            $exists = StudentAttendance::query()
                ->where('student_id', $student->id)
                ->whereDate(
                    'attendance_date',
                    $occurredAt->toDateString()
                )
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'attendance' => 'Siswa sudah melakukan check-in hari ini.',
                ]);
            }

            $attendance = StudentAttendance::query()->create([
                'student_id' => $student->id,
                'class_id' => $enrollment->class_id,
                'attendance_schedule_id' => $schedule->id,
                'school_location_id' => $schedule->school_location_id,
                'attendance_date' => $occurredAt->toDateString(),
                'check_in_at' => $occurredAt,
                'check_in_latitude' => $latitude,
                'check_in_longitude' => $longitude,
                'location_accuracy' => $accuracy,
                'distance_from_school' => round($distance, 2),
                'status' => $status['status'],
                'late_minutes' => $status['late_minutes'],
                'source' => AttendanceSource::Geolocation,
            ]);

            $this->auditLogger->log(
                $actor,
                'attendance',
                'CHECK_IN',
                $attendance,
                null,
                $attendance->getAttributes()
            );

            if ($attendance->status === AttendanceStatus::Late) {
                DB::afterCommit(
                    fn () => event(
                        new StudentLateDetected($attendance->id)
                    )
                );
            }

            return $attendance;
        });
    }

    /**
     * @param Collection<int, AttendanceSchedule> $schedules
     * @return array{0: AttendanceSchedule, 1: float}
     */
    private function resolveLocation(
        Collection $schedules,
        float $latitude,
        float $longitude
    ): array {
        $best = null;

        foreach ($schedules as $schedule) {
            if (! $schedule->schoolLocation) {
                continue;
            }

            $distance = $this->geofence->distanceFromLocation(
                $schedule->schoolLocation,
                $latitude,
                $longitude
            );

            if ($distance <= $schedule->schoolLocation->radius_meters) {
                if ($best === null || $distance < $best[1]) {
                    $best = [$schedule, $distance];
                }
            }
        }

        if ($best === null) {
            throw ValidationException::withMessages([
                'location' => 'Posisi berada di luar area absensi sekolah.',
            ]);
        }

        return $best;
    }

    private function assertCoordinates(
        float $latitude,
        float $longitude
    ): void {
        if (
            $latitude < -90
            || $latitude > 90
            || $longitude < -180
            || $longitude > 180
        ) {
            throw ValidationException::withMessages([
                'location' => 'Koordinat lokasi tidak valid.',
            ]);
        }
    }

    private function assertAccuracy(?float $accuracy): void
    {
        $max = config(
            'school.attendance.max_location_accuracy_meters'
        );

        if ($max !== null && $accuracy !== null && $accuracy > $max) {
            throw ValidationException::withMessages([
                'accuracy' => 'Akurasi GPS terlalu rendah. Silakan coba lagi.',
            ]);
        }
    }
}

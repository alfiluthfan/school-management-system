<?php

namespace App\Actions\Attendance;

use App\Enums\Attendance\AttendanceSource;
use App\Enums\Attendance\AttendanceType;
use App\Enums\Attendance\TeacherLeaveStatus;
use App\Models\Academic\Teacher;
use App\Models\Attendance\AttendanceSchedule;
use App\Models\Attendance\TeacherAttendance;
use App\Models\Attendance\TeacherLeave;
use App\Models\Auth\User;
use App\Services\Attendance\AttendanceScheduleResolver;
use App\Services\Attendance\AttendanceStatusCalculator;
use App\Services\Attendance\GeofenceService;
use App\Services\System\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CheckInTeacherAction
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
        Teacher $teacher,
        float $latitude,
        float $longitude,
        ?float $accuracy = null,
        ?CarbonImmutable $occurredAt = null
    ): TeacherAttendance {
        $occurredAt ??= CarbonImmutable::now(
            config('app.timezone')
        );

        $this->assertCoordinates(
            $latitude,
            $longitude
        );

        $this->assertAccuracy($accuracy);

        $this->assertTeacherIsNotOnApprovedLeave(
            $teacher,
            $occurredAt
        );

        $schedules = $this->scheduleResolver->candidates(
            AttendanceType::Teacher,
            $occurredAt
        );

        [$schedule, $distance] =
            $this->resolveLocation(
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
            $teacher,
            $schedule,
            $distance,
            $status,
            $latitude,
            $longitude,
            $accuracy,
            $occurredAt
        ): TeacherAttendance {
            $exists = TeacherAttendance::query()
                ->where(
                    'teacher_id',
                    $teacher->id
                )
                ->whereDate(
                    'attendance_date',
                    $occurredAt->toDateString()
                )
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'attendance' =>
                        'Guru sudah melakukan check-in hari ini.',
                ]);
            }

            $attendance =
                TeacherAttendance::query()->create([
                    'teacher_id' => $teacher->id,
                    'attendance_schedule_id' =>
                        $schedule->id,
                    'school_location_id' =>
                        $schedule->school_location_id,
                    'attendance_date' =>
                        $occurredAt->toDateString(),
                    'check_in_at' => $occurredAt,
                    'check_out_at' => null,
                    'check_in_latitude' => $latitude,
                    'check_in_longitude' => $longitude,
                    'check_out_latitude' => null,
                    'check_out_longitude' => null,
                    'location_accuracy' => $accuracy,
                    'distance_from_school' =>
                        round($distance, 2),
                    'status' => $status['status'],
                    'late_minutes' =>
                        $status['late_minutes'],
                    'source' =>
                        AttendanceSource::Geolocation,
                    'notes' => null,
                ]);

            $this->auditLogger->log(
                actor: $actor,
                module: 'attendance',
                action: 'TEACHER_CHECK_IN',
                entity: $attendance,
                oldValues: null,
                newValues: $attendance->getAttributes()
            );

            return $attendance->fresh();
        }, 3);
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

            $distance =
                $this->geofence->distanceFromLocation(
                    $schedule->schoolLocation,
                    $latitude,
                    $longitude
                );

            if (
                $distance
                <= $schedule
                    ->schoolLocation
                    ->radius_meters
            ) {
                if (
                    $best === null
                    || $distance < $best[1]
                ) {
                    $best = [
                        $schedule,
                        $distance,
                    ];
                }
            }
        }

        if ($best === null) {
            throw ValidationException::withMessages([
                'location' =>
                    'Posisi berada di luar area absensi sekolah.',
            ]);
        }

        return $best;
    }

    private function assertTeacherIsNotOnApprovedLeave(
        Teacher $teacher,
        CarbonImmutable $occurredAt
    ): void {
        $date = $occurredAt->toDateString();

        $onLeave = TeacherLeave::query()
            ->where('teacher_id', $teacher->id)
            ->where(
                'status',
                TeacherLeaveStatus::Approved->value
            )
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();

        if ($onLeave) {
            throw ValidationException::withMessages([
                'attendance' =>
                    'Guru memiliki izin/cuti APPROVED pada tanggal ini.',
            ]);
        }
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
                'location' =>
                    'Koordinat lokasi tidak valid.',
            ]);
        }
    }

    private function assertAccuracy(
        ?float $accuracy
    ): void {
        $max = config(
            'school.attendance.max_location_accuracy_meters'
        );

        if (
            $max !== null
            && $accuracy !== null
            && $accuracy > $max
        ) {
            throw ValidationException::withMessages([
                'accuracy' =>
                    'Akurasi GPS terlalu rendah. Silakan coba lagi.',
            ]);
        }
    }
}

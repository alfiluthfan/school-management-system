<?php

namespace App\Actions\Attendance;

use App\Models\Academic\Student;
use App\Models\Attendance\StudentAttendance;
use App\Models\Auth\User;
use App\Services\Attendance\AttendanceStatusCalculator;
use App\Services\Attendance\GeofenceService;
use App\Services\System\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CheckOutStudentAction
{
    public function __construct(
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

        return DB::transaction(function () use (
            $actor,
            $student,
            $latitude,
            $longitude,
            $accuracy,
            $occurredAt
        ): StudentAttendance {
            $attendance = StudentAttendance::query()
                ->with(['schedule', 'schoolLocation'])
                ->where('student_id', $student->id)
                ->whereDate(
                    'attendance_date',
                    $occurredAt->toDateString()
                )
                ->lockForUpdate()
                ->first();

            if (! $attendance) {
                throw ValidationException::withMessages([
                    'attendance' => 'Check-in hari ini belum ditemukan.',
                ]);
            }

            if ($attendance->check_out_at !== null) {
                throw ValidationException::withMessages([
                    'attendance' => 'Siswa sudah melakukan check-out.',
                ]);
            }

            if (! $attendance->schedule) {
                throw ValidationException::withMessages([
                    'attendance' => 'Jadwal absensi tidak tersedia.',
                ]);
            }

            $this->statusCalculator->assertCheckOutAllowed(
                $attendance->schedule,
                $occurredAt
            );

            $distance = null;

            if ($attendance->schoolLocation) {
                $distance = $this->geofence->distanceFromLocation(
                    $attendance->schoolLocation,
                    $latitude,
                    $longitude
                );

                if (
                    $distance
                    > $attendance->schoolLocation->radius_meters
                ) {
                    throw ValidationException::withMessages([
                        'location' => 'Posisi berada di luar area sekolah.',
                    ]);
                }
            }

            $old = $attendance->getAttributes();

            $attendance->update([
                'check_out_at' => $occurredAt,
                'check_out_latitude' => $latitude,
                'check_out_longitude' => $longitude,
                'location_accuracy' => $accuracy,
                'distance_from_school' => $distance !== null
                    ? round($distance, 2)
                    : $attendance->distance_from_school,
            ]);

            $this->auditLogger->log(
                $actor,
                'attendance',
                'CHECK_OUT',
                $attendance,
                $old,
                $attendance->getAttributes()
            );

            return $attendance->fresh();
        });
    }
}

<?php

namespace App\Actions\Attendance;

use App\Models\Academic\Teacher;
use App\Models\Attendance\TeacherAttendance;
use App\Models\Auth\User;
use App\Services\Attendance\AttendanceStatusCalculator;
use App\Services\Attendance\GeofenceService;
use App\Services\System\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CheckOutTeacherAction
{
    public function __construct(
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

        return DB::transaction(function () use (
            $actor,
            $teacher,
            $latitude,
            $longitude,
            $accuracy,
            $occurredAt
        ): TeacherAttendance {
            $attendance =
                TeacherAttendance::query()
                    ->with([
                        'schedule',
                        'schoolLocation',
                    ])
                    ->where(
                        'teacher_id',
                        $teacher->id
                    )
                    ->whereDate(
                        'attendance_date',
                        $occurredAt->toDateString()
                    )
                    ->lockForUpdate()
                    ->first();

            if (! $attendance) {
                throw ValidationException::withMessages([
                    'attendance' =>
                        'Check-in guru hari ini belum ditemukan.',
                ]);
            }

            if ($attendance->check_out_at !== null) {
                throw ValidationException::withMessages([
                    'attendance' =>
                        'Guru sudah melakukan check-out.',
                ]);
            }

            if (! $attendance->schedule) {
                throw ValidationException::withMessages([
                    'attendance' =>
                        'Jadwal absensi tidak tersedia.',
                ]);
            }

            $this->statusCalculator
                ->assertCheckOutAllowed(
                    $attendance->schedule,
                    $occurredAt
                );

            $distance = null;

            if ($attendance->schoolLocation) {
                $distance =
                    $this->geofence
                        ->distanceFromLocation(
                            $attendance
                                ->schoolLocation,
                            $latitude,
                            $longitude
                        );

                if (
                    $distance
                    > $attendance
                        ->schoolLocation
                        ->radius_meters
                ) {
                    throw ValidationException::withMessages([
                        'location' =>
                            'Posisi berada di luar area sekolah.',
                    ]);
                }
            }

            $old =
                $attendance->getAttributes();

            $attendance->update([
                'check_out_at' => $occurredAt,
                'check_out_latitude' =>
                    $latitude,
                'check_out_longitude' =>
                    $longitude,
                'location_accuracy' =>
                    $accuracy,
                'distance_from_school' =>
                    $distance !== null
                        ? round($distance, 2)
                        : $attendance
                            ->distance_from_school,
            ]);

            $this->auditLogger->log(
                actor: $actor,
                module: 'attendance',
                action: 'TEACHER_CHECK_OUT',
                entity: $attendance,
                oldValues: $old,
                newValues:
                    $attendance->getAttributes()
            );

            return $attendance->fresh();
        }, 3);
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

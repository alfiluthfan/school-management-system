<?php

namespace App\Services\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\Attendance\StudentAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class AttendanceCorrectionValidator
{
    public function __construct(
        private readonly AttendanceStatusCalculator $statusCalculator
    ) {
    }

    /**
     * @param array<string, mixed> $changes
     * @return array{
     *   status: AttendanceStatus,
     *   check_in_at: ?CarbonImmutable,
     *   check_out_at: ?CarbonImmutable,
     *   late_minutes: int,
     *   notes: ?string
     * }
     */
    public function resolve(
        StudentAttendance $attendance,
        array $changes
    ): array {
        $attendance->loadMissing('schedule');

        $this->assertAllowedKeys($changes);

        $finalStatus = array_key_exists('status', $changes)
            ? AttendanceStatus::from(
                $changes['status'] instanceof AttendanceStatus
                    ? $changes['status']->value
                    : (string) $changes['status']
            )
            : $attendance->status;

        $finalCheckIn = array_key_exists('check_in_at', $changes)
            ? $this->parseNullableDateTime($changes['check_in_at'])
            : ($attendance->check_in_at
                ? CarbonImmutable::instance($attendance->check_in_at)
                : null);

        $finalCheckOut = array_key_exists('check_out_at', $changes)
            ? $this->parseNullableDateTime($changes['check_out_at'])
            : ($attendance->check_out_at
                ? CarbonImmutable::instance($attendance->check_out_at)
                : null);

        if (
            in_array(
                $finalStatus,
                [
                    AttendanceStatus::Sick,
                    AttendanceStatus::Permission,
                    AttendanceStatus::Absent,
                ],
                true
            )
        ) {
            $finalCheckIn = null;
            $finalCheckOut = null;
            $lateMinutes = 0;
        } else {
            if (! $finalCheckIn) {
                throw ValidationException::withMessages([
                    'changes.check_in_at' =>
                        'Status PRESENT/LATE membutuhkan waktu check-in.',
                ]);
            }

            $timeOrStatusChanged =
                array_key_exists('check_in_at', $changes)
                || array_key_exists('status', $changes);

            if (
                $timeOrStatusChanged
                && ! $attendance->schedule
            ) {
                throw ValidationException::withMessages([
                    'attendance' =>
                        'Jadwal absensi diperlukan untuk menghitung ulang status/late_minutes.',
                ]);
            }

            if ($attendance->schedule) {
                $calculated =
                    $this->statusCalculator->forCheckIn(
                        $attendance->schedule,
                        $finalCheckIn
                    );

                $finalStatus =
                    $calculated['status'];
                $lateMinutes =
                    $calculated['late_minutes'];
            } else {
                $lateMinutes =
                    $attendance->late_minutes;
            }
        }

        if (
            $finalCheckIn
            && $finalCheckOut
            && $finalCheckOut->lt($finalCheckIn)
        ) {
            throw ValidationException::withMessages([
                'changes.check_out_at' =>
                    'Waktu check-out tidak boleh lebih awal dari check-in.',
            ]);
        }

        foreach (
            [
                'changes.check_in_at' =>
                    $finalCheckIn,
                'changes.check_out_at' =>
                    $finalCheckOut,
            ]
            as $field => $value
        ) {
            if (
                $value
                && $value->toDateString()
                    !== $attendance
                        ->attendance_date
                        ->toDateString()
            ) {
                throw ValidationException::withMessages([
                    $field =>
                        'Waktu koreksi harus berada pada attendance_date yang sama.',
                ]);
            }
        }

        return [
            'status' => $finalStatus,
            'check_in_at' => $finalCheckIn,
            'check_out_at' => $finalCheckOut,
            'late_minutes' => $lateMinutes,
            'notes' => array_key_exists(
                'notes',
                $changes
            )
                ? $changes['notes']
                : $attendance->notes,
        ];
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function assertAllowedKeys(
        array $changes
    ): void {
        $allowed = [
            'status',
            'check_in_at',
            'check_out_at',
            'notes',
        ];

        if ($changes === []) {
            throw ValidationException::withMessages([
                'changes' =>
                    'Minimal satu field koreksi harus diberikan.',
            ]);
        }

        foreach (array_keys($changes) as $key) {
            if (! in_array($key, $allowed, true)) {
                throw ValidationException::withMessages([
                    'changes.'.$key =>
                        'Field ini tidak dapat dikoreksi melalui approval.',
                ]);
            }
        }
    }

    private function parseNullableDateTime(
        mixed $value
    ): ?CarbonImmutable {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse(
            (string) $value,
            config('app.timezone')
        );
    }
}

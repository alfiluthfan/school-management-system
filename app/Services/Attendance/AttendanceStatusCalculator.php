<?php

namespace App\Services\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\Attendance\AttendanceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class AttendanceStatusCalculator
{
    /**
     * @return array{status: AttendanceStatus, late_minutes: int}
     */
    public function forCheckIn(
        AttendanceSchedule $schedule,
        CarbonImmutable $occurredAt
    ): array {
        $date = $occurredAt->toDateString();

        $start = $this->timeOnDate(
            $date,
            $schedule->check_in_start,
            $occurredAt->timezoneName
        );

        $deadline = $this->timeOnDate(
            $date,
            $schedule->check_in_deadline,
            $occurredAt->timezoneName
        );

        $end = $this->timeOnDate(
            $date,
            $schedule->check_in_end,
            $occurredAt->timezoneName
        );

        if ($start && $occurredAt->lt($start)) {
            throw ValidationException::withMessages([
                'attendance' => 'Waktu check-in belum dibuka.',
            ]);
        }

        if ($end && $occurredAt->gt($end)) {
            throw ValidationException::withMessages([
                'attendance' => 'Waktu check-in sudah ditutup.',
            ]);
        }

        if (! $deadline) {
            return [
                'status' => AttendanceStatus::Present,
                'late_minutes' => 0,
            ];
        }

        $effectiveDeadline = $deadline->addMinutes(
            $schedule->late_tolerance_minutes
        );

        if ($occurredAt->lte($effectiveDeadline)) {
            return [
                'status' => AttendanceStatus::Present,
                'late_minutes' => 0,
            ];
        }

        return [
            'status' => AttendanceStatus::Late,
            'late_minutes' => $deadline->diffInMinutes($occurredAt),
        ];
    }

    public function assertCheckOutAllowed(
        AttendanceSchedule $schedule,
        CarbonImmutable $occurredAt
    ): void {
        $date = $occurredAt->toDateString();

        $start = $this->timeOnDate(
            $date,
            $schedule->check_out_start,
            $occurredAt->timezoneName
        );

        $end = $this->timeOnDate(
            $date,
            $schedule->check_out_end,
            $occurredAt->timezoneName
        );

        if ($start && $occurredAt->lt($start)) {
            throw ValidationException::withMessages([
                'attendance' => 'Waktu check-out belum dibuka.',
            ]);
        }

        if ($end && $occurredAt->gt($end)) {
            throw ValidationException::withMessages([
                'attendance' => 'Waktu check-out sudah ditutup.',
            ]);
        }
    }

    private function timeOnDate(
        string $date,
        ?string $time,
        string $timezone
    ): ?CarbonImmutable {
        if (! $time) {
            return null;
        }

        return CarbonImmutable::parse(
            $date.' '.$time,
            $timezone
        );
    }
}

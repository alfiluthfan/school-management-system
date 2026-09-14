<?php

namespace App\Services\Attendance;

use App\Enums\Attendance\AttendanceType;
use App\Models\Academic\AcademicYear;
use App\Models\Attendance\AttendanceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class AttendanceScheduleResolver
{
    /**
     * @return Collection<int, AttendanceSchedule>
     */
    public function candidates(
        AttendanceType $type,
        CarbonImmutable $occurredAt
    ): Collection {
        $academicYear = AcademicYear::query()
            ->where('is_active', true)
            ->first();

        if (! $academicYear) {
            throw ValidationException::withMessages([
                'academic_year' => 'Tidak ada tahun ajaran aktif.',
            ]);
        }

        $schedules = AttendanceSchedule::query()
            ->with('schoolLocation')
            ->where('attendance_type', $type->value)
            ->where('academic_year_id', $academicYear->id)
            ->where('day_of_week', $occurredAt->isoWeekday())
            ->where('is_active', true)
            ->where(function ($query) use ($occurredAt): void {
                $query->whereNull('effective_from')
                    ->orWhereDate(
                        'effective_from',
                        '<=',
                        $occurredAt->toDateString()
                    );
            })
            ->where(function ($query) use ($occurredAt): void {
                $query->whereNull('effective_until')
                    ->orWhereDate(
                        'effective_until',
                        '>=',
                        $occurredAt->toDateString()
                    );
            })
            ->get();

        if ($schedules->isEmpty()) {
            throw ValidationException::withMessages([
                'attendance' => 'Jadwal absensi aktif tidak ditemukan.',
            ]);
        }

        return $schedules;
    }
}

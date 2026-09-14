<?php

namespace App\Enums\System;

use App\Enums\Concerns\HasEnumOptions;

enum ApprovalModule: string
{
    use HasEnumOptions;

    case Saving = 'SAVING';
    case Attendance = 'ATTENDANCE';
    case TeacherLeave = 'TEACHER_LEAVE';
    case Spp = 'SPP';

    public function label(): string
    {
        return match ($this) {
            self::Saving => 'Tabungan',
            self::Attendance => 'Absensi',
            self::TeacherLeave => 'Izin Guru',
            self::Spp => 'SPP',
        };
    }
}

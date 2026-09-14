<?php

namespace App\Enums\Attendance;

use App\Enums\Concerns\HasEnumOptions;

enum AttendanceStatus: string
{
    use HasEnumOptions;

    case Present = 'PRESENT';
    case Late = 'LATE';
    case Sick = 'SICK';
    case Permission = 'PERMISSION';
    case Absent = 'ABSENT';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::Sick => 'Sakit',
            self::Permission => 'Izin',
            self::Absent => 'Tidak Hadir',
        };
    }
}

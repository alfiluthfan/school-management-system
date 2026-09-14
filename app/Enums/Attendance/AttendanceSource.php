<?php

namespace App\Enums\Attendance;

use App\Enums\Concerns\HasEnumOptions;

enum AttendanceSource: string
{
    use HasEnumOptions;

    case Geolocation = 'GEOLOCATION';
    case Manual = 'MANUAL';
    case System = 'SYSTEM';

    public function label(): string
    {
        return match ($this) {
            self::Geolocation => 'Geolokasi',
            self::Manual => 'Manual',
            self::System => 'Sistem',
        };
    }
}

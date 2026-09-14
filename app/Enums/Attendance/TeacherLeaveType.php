<?php

namespace App\Enums\Attendance;

use App\Enums\Concerns\HasEnumOptions;

enum TeacherLeaveType: string
{
    use HasEnumOptions;

    case Sick = 'SICK';
    case Permission = 'PERMISSION';
    case AnnualLeave = 'ANNUAL_LEAVE';
    case OfficialDuty = 'OFFICIAL_DUTY';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Sick => 'Sakit',
            self::Permission => 'Izin',
            self::AnnualLeave => 'Cuti Tahunan',
            self::OfficialDuty => 'Dinas',
            self::Other => 'Lainnya',
        };
    }
}

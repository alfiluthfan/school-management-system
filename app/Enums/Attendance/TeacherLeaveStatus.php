<?php

namespace App\Enums\Attendance;

use App\Enums\Concerns\HasEnumOptions;

enum TeacherLeaveStatus: string
{
    use HasEnumOptions;

    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }
}

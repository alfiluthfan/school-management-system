<?php

namespace App\Enums\Communication;

use App\Enums\Concerns\HasEnumOptions;

enum NotificationType: string
{
    use HasEnumOptions;

    case StudentLate = 'STUDENT_LATE';
    case SppOverdue = 'SPP_OVERDUE';
    case SppPaid = 'SPP_PAID';
    case Announcement = 'ANNOUNCEMENT';

    public function label(): string
    {
        return match ($this) {
            self::StudentLate => 'Siswa Terlambat',
            self::SppOverdue => 'SPP Menunggak',
            self::SppPaid => 'SPP Dibayar',
            self::Announcement => 'Pengumuman',
        };
    }
}

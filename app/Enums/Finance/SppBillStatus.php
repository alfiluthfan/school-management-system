<?php

namespace App\Enums\Finance;

use App\Enums\Concerns\HasEnumOptions;

enum SppBillStatus: string
{
    use HasEnumOptions;

    case Pending = 'PENDING';
    case Partial = 'PARTIAL';
    case Paid = 'PAID';
    case Overdue = 'OVERDUE';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum Dibayar',
            self::Partial => 'Dibayar Sebagian',
            self::Paid => 'Lunas',
            self::Overdue => 'Menunggak',
            self::Cancelled => 'Dibatalkan',
        };
    }
}

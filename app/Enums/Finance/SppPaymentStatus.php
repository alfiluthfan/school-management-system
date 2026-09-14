<?php

namespace App\Enums\Finance;

use App\Enums\Concerns\HasEnumOptions;

enum SppPaymentStatus: string
{
    use HasEnumOptions;

    case Pending = 'PENDING';
    case Posted = 'POSTED';
    case Void = 'VOID';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Posted => 'Tercatat',
            self::Void => 'Dibatalkan',
        };
    }
}

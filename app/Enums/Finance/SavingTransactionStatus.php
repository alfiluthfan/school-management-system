<?php

namespace App\Enums\Finance;

use App\Enums\Concerns\HasEnumOptions;

enum SavingTransactionStatus: string
{
    use HasEnumOptions;

    case Pending = 'PENDING';
    case Posted = 'POSTED';
    case Reversed = 'REVERSED';
    case Void = 'VOID';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Posted => 'Tercatat',
            self::Reversed => 'Dibalikkan',
            self::Void => 'Dibatalkan',
        };
    }
}

<?php

namespace App\Enums\Finance;

use App\Enums\Concerns\HasEnumOptions;

enum SavingTransactionType: string
{
    use HasEnumOptions;

    case Deposit = 'DEPOSIT';
    case Withdrawal = 'WITHDRAWAL';
    case Reversal = 'REVERSAL';
    case Adjustment = 'ADJUSTMENT';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Setoran',
            self::Withdrawal => 'Penarikan',
            self::Reversal => 'Reversal',
            self::Adjustment => 'Penyesuaian',
        };
    }
}

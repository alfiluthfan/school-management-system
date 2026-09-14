<?php

namespace App\Enums\Finance;

use App\Enums\Concerns\HasEnumOptions;

enum PaymentMethod: string
{
    use HasEnumOptions;

    case Cash = 'CASH';
    case BankTransfer = 'BANK_TRANSFER';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::BankTransfer => 'Transfer Bank',
            self::Other => 'Lainnya',
        };
    }
}

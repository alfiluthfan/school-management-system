<?php

namespace App\Enums\Finance;

use App\Enums\Concerns\HasEnumOptions;

enum SavingAccountStatus: string
{
    use HasEnumOptions;

    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Closed = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Suspended => 'Ditangguhkan',
            self::Closed => 'Ditutup',
        };
    }
}

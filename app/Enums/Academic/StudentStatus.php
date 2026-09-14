<?php

namespace App\Enums\Academic;

use App\Enums\Concerns\HasEnumOptions;

enum StudentStatus: string
{
    use HasEnumOptions;

    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Graduated = 'GRADUATED';
    case Transferred = 'TRANSFERRED';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Tidak Aktif',
            self::Graduated => 'Lulus',
            self::Transferred => 'Pindah',
        };
    }
}

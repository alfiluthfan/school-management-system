<?php

namespace App\Enums\Academic;

use App\Enums\Concerns\HasEnumOptions;

enum SchoolClassStatus: string
{
    use HasEnumOptions;

    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Archived = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Tidak Aktif',
            self::Archived => 'Diarsipkan',
        };
    }
}

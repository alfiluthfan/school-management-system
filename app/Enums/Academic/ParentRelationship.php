<?php

namespace App\Enums\Academic;

use App\Enums\Concerns\HasEnumOptions;

enum ParentRelationship: string
{
    use HasEnumOptions;

    case Father = 'FATHER';
    case Mother = 'MOTHER';
    case Guardian = 'GUARDIAN';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Ayah',
            self::Mother => 'Ibu',
            self::Guardian => 'Wali',
        };
    }
}

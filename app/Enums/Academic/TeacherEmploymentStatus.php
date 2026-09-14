<?php

namespace App\Enums\Academic;

use App\Enums\Concerns\HasEnumOptions;

enum TeacherEmploymentStatus: string
{
    use HasEnumOptions;

    case Permanent = 'PERMANENT';
    case Contract = 'CONTRACT';
    case Honorary = 'HONORARY';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'Tetap',
            self::Contract => 'Kontrak',
            self::Honorary => 'Honorer',
        };
    }
}

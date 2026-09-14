<?php

namespace App\Enums\Common;

use App\Enums\Concerns\HasEnumOptions;

enum Gender: string
{
    use HasEnumOptions;

    case Male = 'MALE';
    case Female = 'FEMALE';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Laki-laki',
            self::Female => 'Perempuan',
        };
    }
}

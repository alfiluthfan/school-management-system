<?php

namespace App\Enums\Academic;

use App\Enums\Concerns\HasEnumOptions;

enum TeacherStatus: string
{
    use HasEnumOptions;

    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Resigned = 'RESIGNED';
    case Retired = 'RETIRED';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Tidak Aktif',
            self::Resigned => 'Mengundurkan Diri',
            self::Retired => 'Pensiun',
        };
    }
}

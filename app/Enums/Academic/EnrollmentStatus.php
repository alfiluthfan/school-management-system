<?php

namespace App\Enums\Academic;

use App\Enums\Concerns\HasEnumOptions;

enum EnrollmentStatus: string
{
    use HasEnumOptions;

    case Active = 'ACTIVE';
    case Completed = 'COMPLETED';
    case Transferred = 'TRANSFERRED';
    case Withdrawn = 'WITHDRAWN';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Completed => 'Selesai',
            self::Transferred => 'Pindah',
            self::Withdrawn => 'Keluar',
        };
    }
}

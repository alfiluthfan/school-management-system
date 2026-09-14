<?php

namespace App\Enums\Communication;

use App\Enums\Concerns\HasEnumOptions;

enum AnnouncementTargetScope: string
{
    use HasEnumOptions;

    case School = 'SCHOOL';
    case Classroom = 'CLASS';

    public function label(): string
    {
        return match ($this) {
            self::School => 'Sekolah',
            self::Classroom => 'Kelas',
        };
    }
}

<?php

namespace App\Enums\Communication;

use App\Enums\Concerns\HasEnumOptions;

enum AnnouncementStatus: string
{
    use HasEnumOptions;

    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
    case Archived = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Dipublikasikan',
            self::Archived => 'Diarsipkan',
        };
    }
}

<?php

namespace App\Enums\Communication;

use App\Enums\Concerns\HasEnumOptions;

enum NotificationChannel: string
{
    use HasEnumOptions;

    case WhatsApp = 'WHATSAPP';
    case Email = 'EMAIL';
    case InApp = 'IN_APP';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Email => 'Email',
            self::InApp => 'Dalam Aplikasi',
        };
    }
}

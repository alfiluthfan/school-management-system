<?php

namespace App\Enums\Communication;

use App\Enums\Concerns\HasEnumOptions;

enum NotificationStatus: string
{
    use HasEnumOptions;

    case Queued = 'QUEUED';
    case Processing = 'PROCESSING';
    case Sent = 'SENT';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Dalam Antrean',
            self::Processing => 'Diproses',
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal',
            self::Cancelled => 'Dibatalkan',
        };
    }
}

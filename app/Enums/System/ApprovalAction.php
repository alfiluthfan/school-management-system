<?php

namespace App\Enums\System;

use App\Enums\Concerns\HasEnumOptions;

enum ApprovalAction: string
{
    use HasEnumOptions;

    case Correction = 'CORRECTION';
    case Void = 'VOID';
    case LeaveRequest = 'LEAVE_REQUEST';
    case SensitiveUpdate = 'SENSITIVE_UPDATE';

    public function label(): string
    {
        return match ($this) {
            self::Correction => 'Koreksi',
            self::Void => 'Pembatalan Transaksi',
            self::LeaveRequest => 'Pengajuan Izin',
            self::SensitiveUpdate => 'Perubahan Data Sensitif',
        };
    }
}

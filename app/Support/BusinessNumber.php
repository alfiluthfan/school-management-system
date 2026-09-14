<?php

namespace App\Support;

use Illuminate\Support\Str;

final class BusinessNumber
{
    public static function savingTransaction(): string
    {
        return self::make('SAV');
    }

    public static function sppPayment(): string
    {
        return self::make('PAY');
    }

    public static function sppReceipt(): string
    {
        return self::make('RCT');
    }

    private static function make(string $prefix): string
    {
        return sprintf(
            '%s-%s-%s',
            $prefix,
            now()->format('Ymd'),
            Str::upper((string) Str::ulid())
        );
    }
}

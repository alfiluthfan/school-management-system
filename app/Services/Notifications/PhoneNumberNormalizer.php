<?php

namespace App\Services\Notifications;

use InvalidArgumentException;

final class PhoneNumberNormalizer
{
    public function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException(
                'Nomor WhatsApp penerima kosong.'
            );
        }

        $countryCode = (string) config(
            'school-notifications.whatsapp.default_country_calling_code',
            '62'
        );

        if (str_starts_with($digits, '0')) {
            $digits = $countryCode.substr($digits, 1);
        }

        /*
         * E.164 supports a maximum of 15 digits.
         * A conservative lower bound prevents obviously invalid values.
         */
        if (
            strlen($digits) < 8
            || strlen($digits) > 15
        ) {
            throw new InvalidArgumentException(
                'Format nomor WhatsApp penerima tidak valid.'
            );
        }

        return $digits;
    }
}

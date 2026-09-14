<?php

namespace App\Enums\Concerns;

trait HasEnumOptions
{
    public static function values(): array
    {
        return array_map(static fn ($case): string => $case->value, self::cases());
    }

    public static function options(): array
    {
        return array_map(
            static fn ($case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }
}

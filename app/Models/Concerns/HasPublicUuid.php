<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

trait HasPublicUuid
{
    use HasUuids;

    /**
     * Keep the internal bigint "id" as the primary key while Laravel
     * automatically fills the public "uuid" column (UUIDv7 on Laravel 13).
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Use UUID for implicit route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}

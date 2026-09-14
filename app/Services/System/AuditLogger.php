<?php

namespace App\Services\System;

use App\Models\Auth\User;
use App\Models\System\AuditLog;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    public function log(
        ?User $actor,
        string $module,
        string $action,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $metadata = []
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'module' => $module,
            'action' => $action,
            'entity_type' => $entity?->getMorphClass(),
            'entity_id' => $entity?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_id' => request()->header('X-Request-ID'),
        ]);
    }
}

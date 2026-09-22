<?php

namespace App\Support\Operations;

/** Pure assessment: no credentials, personal data or backend exception messages. */
final class OperationsAssessment
{
    /** @return array<int, array{code: string, ok: bool, observed: int|bool|null, limit: int|bool}> */
    public function assess(array $readings, array $limits, bool $requireHeartbeats): array
    {
        $checks = [];
        $boolean = function (string $name) use (&$checks, $readings): void {
            $value = $readings[$name] ?? false;
            $checks[] = ['code' => $name, 'ok' => $value === true, 'observed' => $value === true, 'limit' => true];
        };
        $maximum = function (string $name, string $limitKey, int $default) use (&$checks, $readings, $limits): void {
            $value = $readings[$name] ?? null;
            $limit = max(0, (int) ($limits[$limitKey] ?? $default));
            $checks[] = [
                'code' => $name,
                'ok' => is_int($value) && $value >= 0 && $value <= $limit,
                'observed' => is_int($value) ? $value : null,
                'limit' => $limit,
            ];
        };

        $boolean('database');
        $boolean('redis_exports');
        $boolean('redis_notifications');
        $boolean('cache');
        $maximum('exports_depth', 'max_queue_depth', 100);
        $maximum('notifications_depth', 'max_queue_depth', 100);
        $maximum('stale_queued_exports', 'max_stale_queued', 0);
        $maximum('stale_processing_exports', 'max_stale_processing', 0);
        $maximum('failed_jobs_24h', 'max_failed_jobs_24h', 0);

        if ($requireHeartbeats) {
            foreach (['scheduler_age', 'exports_worker_age', 'notifications_worker_age'] as $name) {
                $maximum($name, 'max_heartbeat_age_seconds', 240);
            }
        }

        return $checks;
    }
}

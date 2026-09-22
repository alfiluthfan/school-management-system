<?php

namespace App\Console\Commands;

use App\Jobs\Operations\MarkQueueWorkerHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Execute via the normal scheduler each minute, not a public HTTP endpoint. */
final class PortalOpsBeat extends Command
{
    protected $signature = 'portal:ops:beat';
    protected $description = 'Record scheduler heartbeat and enqueue export and notification worker probes.';

    public function handle(): int
    {
        try {
            // Calling dispatch on the real Redis connection ensures a missing broker fails.
            MarkQueueWorkerHeartbeat::dispatch('exports', (string) config('report_exports.queue', 'exports'))
                ->onConnection('redis');
            MarkQueueWorkerHeartbeat::dispatch('notifications', (string) config('school-notifications.queue', 'notifications'))
                ->onConnection('redis');
            $stored = Cache::put(
                (string) config('portal_operations.cache_keys.scheduler'),
                now()->timestamp,
                (int) config('portal_operations.heartbeat_ttl_seconds', 7200)
            );
            if ($stored === false) {
                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->error('Operational heartbeat failed; inspect restricted service logs.');
            return self::FAILURE;
        }

        $this->line('Operational heartbeat submitted.');
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Support\Operations\OperationsAssessment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Read-only operational check. Exit != 0 should alert an EXTERNAL monitoring system. */
final class PortalOpsCheck extends Command
{
    protected $signature = 'portal:ops:check {--json : Output sanitized machine-readable results} {--require-heartbeats : Require scheduler and both workers to have processed recent probes}';
    protected $description = 'Check database, queues, failures, export backlog and scheduler/worker heartbeats.';

    public function handle(OperationsAssessment $assessment): int
    {
        $limits = (array) config('portal_operations', []);
        $readings = [
            'database' => false, 'redis_exports' => false, 'redis_notifications' => false,
            'cache' => false, 'exports_depth' => null, 'notifications_depth' => null,
            'stale_queued_exports' => null, 'stale_processing_exports' => null,
            'failed_jobs_24h' => null, 'scheduler_age' => null,
            'exports_worker_age' => null, 'notifications_worker_age' => null,
        ];

        try {
            DB::select('SELECT 1');
            $readings['database'] = true;
            if (Schema::hasTable('report_exports')) {
                $readings['stale_queued_exports'] = DB::table('report_exports')
                    ->where('status', 'QUEUED')
                    ->where('created_at', '<', now()->subSeconds(max(1, (int) ($limits['queued_stale_after_seconds'] ?? 900))))
                    ->count();
                $readings['stale_processing_exports'] = DB::table('report_exports')
                    ->where('status', 'PROCESSING')
                    ->where(function ($query) use ($limits): void {
                        $query->whereNull('started_at')
                            ->orWhere('started_at', '<', now()->subSeconds(max(1, (int) ($limits['processing_stale_after_seconds'] ?? 900))));
                    })->count();
            }
            if (Schema::hasTable('failed_jobs')) {
                $readings['failed_jobs_24h'] = DB::table('failed_jobs')
                    ->where('failed_at', '>=', now()->subDay())->count();
            }
        } catch (Throwable) {
            // Intentionally do not expose SQL, connection info or exception messages.
            $readings['database'] = false;
        }

        foreach (['exports', 'notifications'] as $channel) {
            try {
                $queue = (string) ($channel === 'exports'
                    ? config('report_exports.queue', 'exports')
                    : config('school-notifications.queue', 'notifications'));
                if ($queue === '') {
                    continue;
                }
                $readings[$channel.'_depth'] = Queue::connection('redis')->size($queue);
                $readings['redis_'.$channel] = true;
            } catch (Throwable) {
                $readings['redis_'.$channel] = false;
            }
        }

        try {
            $readings['cache'] = true;
            foreach (['scheduler' => 'scheduler_age', 'exports' => 'exports_worker_age', 'notifications' => 'notifications_worker_age'] as $channel => $metric) {
                $stamp = Cache::get((string) config('portal_operations.cache_keys.'.$channel));
                $epoch = is_int($stamp) ? $stamp : (is_string($stamp) && ctype_digit($stamp) ? (int) $stamp : null);
                $age = $epoch !== null ? now()->timestamp - $epoch : null;
                // Reject invalid or future timestamps instead of treating them as healthy.
                $readings[$metric] = $age !== null && $age >= 0 ? $age : null;
            }
        } catch (Throwable) {
            $readings['cache'] = false;
        }

        $required = (bool) $this->option('require-heartbeats')
            || (bool) ($limits['heartbeat_required'] ?? false);
        $checks = $assessment->assess($readings, $limits, $required);
        $healthy = ! in_array(false, array_column($checks, 'ok'), true);

        if ($this->option('json')) {
            $this->line(json_encode([
                'status' => $healthy ? 'ok' : 'fail',
                'checked_at' => now()->toIso8601String(),
                'checks' => $checks,
            ], JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $check) {
                $this->line(($check['ok'] ? '[PASS] ' : '[FAIL] ').$check['code']
                    .' observed='.($check['observed'] === null ? 'unavailable' : (is_bool($check['observed']) ? ($check['observed'] ? 'true' : 'false') : $check['observed']))
                    .' limit='.(is_bool($check['limit']) ? 'true' : $check['limit']));
            }
            $this->line($healthy ? 'Operational checks passed.' : 'Operational checks failed; inspect private service dashboards and logs.');
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}

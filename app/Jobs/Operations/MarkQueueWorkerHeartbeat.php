<?php

namespace App\Jobs\Operations;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

/** A small job proves that the named queue is being consumed, not merely reachable. */
final class MarkQueueWorkerHeartbeat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 10;

    public function __construct(public readonly string $channel, string $queueName)
    {
        if (! in_array($channel, ['exports', 'notifications'], true) || $queueName === '') {
            throw new InvalidArgumentException('Unknown operational queue.');
        }

        $this->onConnection('redis')->onQueue($queueName);
    }

    public function handle(): void
    {
        // This runs INSIDE the queue worker. Never store a user, token or payload.
        $stored = Cache::put(
            (string) config('portal_operations.cache_keys.'.$this->channel),
            now()->timestamp,
            (int) config('portal_operations.heartbeat_ttl_seconds', 7200)
        );
        if ($stored === false) {
            throw new RuntimeException('Could not persist operational heartbeat.');
        }
    }
}

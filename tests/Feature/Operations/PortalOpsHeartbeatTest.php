<?php

namespace Tests\Feature\Operations;

use App\Jobs\Operations\MarkQueueWorkerHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class PortalOpsHeartbeatTest extends TestCase
{
    public function test_scheduler_heartbeat_dispatches_probes_to_both_existing_queues(): void
    {
        Queue::fake();
        config()->set('portal_operations.cache_keys.scheduler', 'test:portal:scheduler');
        config()->set('report_exports.queue', 'exports');
        config()->set('school-notifications.queue', 'notifications');

        $this->artisan('portal:ops:beat')->assertExitCode(0);
        Queue::assertPushed(MarkQueueWorkerHeartbeat::class, 2);
        Queue::assertPushed(MarkQueueWorkerHeartbeat::class, fn ($job) => $job->channel === 'exports' && $job->queue === 'exports');
        Queue::assertPushed(MarkQueueWorkerHeartbeat::class, fn ($job) => $job->channel === 'notifications' && $job->queue === 'notifications');
        $this->assertIsInt(Cache::get('test:portal:scheduler'));
    }

    public function test_processed_probe_writes_only_its_queue_heartbeat(): void
    {
        config()->set('portal_operations.cache_keys.exports', 'test:portal:exports');
        config()->set('portal_operations.cache_keys.notifications', 'test:portal:notifications');
        Cache::forget('test:portal:notifications');
        (new MarkQueueWorkerHeartbeat('exports', 'exports'))->handle();
        $this->assertIsInt(Cache::get('test:portal:exports'));
        $this->assertNull(Cache::get('test:portal:notifications'));
    }
}

<?php

namespace Tests\Unit\Operations;

use App\Support\Operations\OperationsAssessment;
use PHPUnit\Framework\TestCase;

final class OperationsAssessmentTest extends TestCase
{
    private function healthyReadings(): array
    {
        return [
            'database' => true, 'redis_exports' => true,
            'redis_notifications' => true, 'cache' => true,
            'exports_depth' => 2, 'notifications_depth' => 1,
            'stale_queued_exports' => 0, 'stale_processing_exports' => 0,
            'failed_jobs_24h' => 0,
            'scheduler_age' => 12, 'exports_worker_age' => 8,
            'notifications_worker_age' => 10,
        ];
    }

    private function assess(array $readings, bool $requireHeartbeats = true): array
    {
        return (new OperationsAssessment())->assess($readings, [
            'max_queue_depth' => 100,
            'max_stale_queued' => 0,
            'max_stale_processing' => 0,
            'max_failed_jobs_24h' => 0,
            'max_heartbeat_age_seconds' => 240,
        ], $requireHeartbeats);
    }

    public function test_healthy_metrics_pass_with_required_worker_heartbeats(): void
    {
        $checks = $this->assess($this->healthyReadings());
        $this->assertCount(12, $checks);
        $this->assertNotContains(false, array_column($checks, 'ok'));
    }

    public function test_stale_queue_and_worker_heartbeat_fail_independently(): void
    {
        $readings = $this->healthyReadings();
        $readings['stale_processing_exports'] = 1;
        $readings['exports_worker_age'] = 500;
        $checks = array_column($this->assess($readings), null, 'code');
        $this->assertFalse($checks['stale_processing_exports']['ok']);
        $this->assertFalse($checks['exports_worker_age']['ok']);
        $this->assertTrue($checks['notifications_worker_age']['ok']);
    }

    public function test_unavailable_database_and_queue_metrics_cannot_be_reported_healthy(): void
    {
        $readings = $this->healthyReadings();
        $readings['database'] = false;
        $readings['failed_jobs_24h'] = null;
        $readings['redis_notifications'] = false;
        $readings['notifications_depth'] = null;
        $checks = array_column($this->assess($readings), null, 'code');
        foreach (['database', 'failed_jobs_24h', 'redis_notifications', 'notifications_depth'] as $name) {
            $this->assertFalse($checks[$name]['ok']);
        }
    }

    public function test_optional_heartbeat_mode_is_explicit_and_does_not_hide_other_failures(): void
    {
        $readings = $this->healthyReadings();
        $readings['scheduler_age'] = null;
        $readings['exports_worker_age'] = null;
        $readings['notifications_worker_age'] = null;
        $readings['failed_jobs_24h'] = 1;
        $checks = $this->assess($readings, false);
        $this->assertCount(9, $checks);
        $this->assertFalse(array_column($checks, null, 'code')['failed_jobs_24h']['ok']);
    }
}

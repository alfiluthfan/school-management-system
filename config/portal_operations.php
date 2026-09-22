<?php

return [
    // Keep the cache shared between scheduler, web and all worker hosts.
    'heartbeat_required' => (bool) env('PORTAL_OPS_REQUIRE_HEARTBEATS', false),
    'heartbeat_ttl_seconds' => 7200,
    'max_heartbeat_age_seconds' => (int) env('PORTAL_OPS_HEARTBEAT_MAX_AGE', 240),
    'max_queue_depth' => (int) env('PORTAL_OPS_MAX_QUEUE_DEPTH', 100),
    'max_stale_queued' => (int) env('PORTAL_OPS_MAX_STALE_QUEUED', 0),
    'max_stale_processing' => (int) env('PORTAL_OPS_MAX_STALE_PROCESSING', 0),
    'max_failed_jobs_24h' => (int) env('PORTAL_OPS_MAX_FAILED_JOBS_24H', 0),
    'queued_stale_after_seconds' => (int) env('PORTAL_OPS_QUEUED_STALE_AFTER', 900),
    'processing_stale_after_seconds' => (int) env('PORTAL_OPS_PROCESSING_STALE_AFTER', 900),
    'cache_keys' => [
        'scheduler' => 'portal:ops:scheduler:last_seen',
        'exports' => 'portal:ops:exports:last_seen',
        'notifications' => 'portal:ops:notifications:last_seen',
    ],
];

<?php

return [
    'disk' => env('REPORT_EXPORT_DISK', 'report_exports'),
    'queue' => env('REPORT_EXPORT_QUEUE', 'exports'),
    'retention_hours' => (int) env('REPORT_EXPORT_RETENTION_HOURS', 48),
];

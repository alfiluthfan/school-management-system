<?php

return [
    'queue' => env('SCHOOL_NOTIFICATION_QUEUE', 'notifications'),

    'spp_overdue' => [
        'enabled' => env(
            'SPP_OVERDUE_SCHEDULER_ENABLED',
            true
        ),

        'schedule_time' => env(
            'SPP_OVERDUE_SCHEDULE_TIME',
            '07:00'
        ),

        'first_reminder_after_days' => (int) env(
            'SPP_OVERDUE_FIRST_REMINDER_AFTER_DAYS',
            1
        ),

        'reminder_interval_days' => (int) env(
            'SPP_OVERDUE_REMINDER_INTERVAL_DAYS',
            3
        ),

        'chunk_size' => (int) env(
            'SPP_OVERDUE_CHUNK_SIZE',
            200
        ),
    ],

    'whatsapp' => [
        /*
         * "log" is safe for local development.
         * Set WHATSAPP_DRIVER=meta when Cloud API credentials are configured.
         */
        'driver' => env('WHATSAPP_DRIVER', 'log'),

        /*
         * Used to normalize local phone numbers such as 0812... -> 62812...
         */
        'default_country_calling_code' => env(
            'WHATSAPP_DEFAULT_COUNTRY_CALLING_CODE',
            '62'
        ),

        'meta' => [
            'base_url' => env(
                'WHATSAPP_GRAPH_BASE_URL',
                'https://graph.facebook.com'
            ),
            'version' => env('WHATSAPP_GRAPH_VERSION'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'timeout_seconds' => (int) env(
                'WHATSAPP_TIMEOUT_SECONDS',
                15
            ),
        ],
    ],
];

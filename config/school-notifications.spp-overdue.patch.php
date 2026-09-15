<?php

/*
|--------------------------------------------------------------------------
| Merge this key into config/school-notifications.php
|--------------------------------------------------------------------------
*/

return [
    'spp_overdue' => [
        'enabled' => env(
            'SPP_OVERDUE_SCHEDULER_ENABLED',
            true
        ),

        /*
         * Scheduler clock time in the application's timezone.
         */
        'schedule_time' => env(
            'SPP_OVERDUE_SCHEDULE_TIME',
            '07:00'
        ),

        /*
         * First reminder is sent N days after due_date.
         * With default 1, a bill due Sep 10 is first reminded Sep 11.
         */
        'first_reminder_after_days' => (int) env(
            'SPP_OVERDUE_FIRST_REMINDER_AFTER_DAYS',
            1
        ),

        /*
         * Default cadence:
         * overdue day 1, 4, 7, 10, ...
         */
        'reminder_interval_days' => (int) env(
            'SPP_OVERDUE_REMINDER_INTERVAL_DAYS',
            3
        ),

        'chunk_size' => (int) env(
            'SPP_OVERDUE_CHUNK_SIZE',
            200
        ),
    ],
];

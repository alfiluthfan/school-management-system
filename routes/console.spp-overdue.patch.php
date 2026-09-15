<?php

use Illuminate\Support\Facades\Schedule;

if (
    (bool) config(
        'school-notifications.spp_overdue.enabled',
        true
    )
) {
    Schedule::command('spp:process-overdue')
        ->dailyAt(
            (string) config(
                'school-notifications.spp_overdue.schedule_time',
                '07:00'
            )
        )
        ->timezone(config('app.timezone'))
        ->withoutOverlapping(30)
        ->onOneServer();
}

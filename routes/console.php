<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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

    Schedule::command('reports:prune-exports')->dailyAt('02:00')->withoutOverlapping();
    Schedule::command('portal:ops:beat')->everyMinute()->withoutOverlapping();
    Schedule::command('spp:generate-monthly')
        ->monthlyOn(1, '00:01')
        ->timezone('Asia/Jakarta')
        ->withoutOverlapping();
}

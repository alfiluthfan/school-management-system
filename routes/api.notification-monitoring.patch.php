<?php

use App\Http\Controllers\Api\V1\Admin\NotificationMonitoringController;
use Illuminate\Support\Facades\Route;

/*
Merge inside the existing:
Route::prefix('v1')->middleware('auth')->group(...)
*/

Route::prefix('admin/notifications')->group(function (): void {
    Route::get('/', [NotificationMonitoringController::class, 'index'])
        ->name('api.v1.admin.notifications.index');

    Route::get('/stats', [NotificationMonitoringController::class, 'stats'])
        ->name('api.v1.admin.notifications.stats');

    Route::get('/{notificationLog}', [NotificationMonitoringController::class, 'show'])
        ->name('api.v1.admin.notifications.show');

    Route::post('/{notificationLog}/retry', [NotificationMonitoringController::class, 'retry'])
        ->name('api.v1.admin.notifications.retry');
});

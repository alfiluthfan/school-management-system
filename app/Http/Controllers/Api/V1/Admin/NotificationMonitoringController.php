<?php
namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Notifications\RetryFailedNotificationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\NotificationLogIndexRequest;
use App\Http\Requests\Notifications\NotificationStatsRequest;
use App\Http\Resources\Notifications\NotificationLogResource;
use App\Http\Resources\Notifications\NotificationStatsResource;
use App\Models\Communication\NotificationLog;
use App\Services\Notifications\NotificationMonitoringService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class NotificationMonitoringController extends Controller
{
    public function index(
        NotificationLogIndexRequest $request,
        NotificationMonitoringService $service
    ): AnonymousResourceCollection {
        Gate::authorize('notification.view.logs');

        return NotificationLogResource::collection(
            $service->paginate($request->validated(), $request->perPage())
        );
    }

    public function stats(
        NotificationStatsRequest $request,
        NotificationMonitoringService $service
    ): NotificationStatsResource {
        Gate::authorize('notification.view.logs');

        return NotificationStatsResource::make(
            $service->stats($request->validated())
        );
    }

    public function show(NotificationLog $notificationLog): NotificationLogResource
    {
        Gate::authorize('notification.view.logs');

        $notificationLog->load(['recipientUser','student.user']);

        return NotificationLogResource::make($notificationLog);
    }

    public function retry(
        NotificationLog $notificationLog,
        RetryFailedNotificationAction $action
    ): NotificationLogResource {
        Gate::authorize('notification.send.manual');

        $notification = $action->execute(
            request()->user(),
            $notificationLog
        );

        $notification->load(['recipientUser','student.user']);

        return NotificationLogResource::make($notification);
    }
}

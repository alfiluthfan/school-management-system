<?php
namespace App\Actions\Notifications;

use App\Enums\Communication\NotificationStatus;
use App\Jobs\Notifications\SendWhatsAppNotificationJob;
use App\Models\Auth\User;
use App\Models\Communication\NotificationLog;
use App\Services\System\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RetryFailedNotificationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(User $actor, NotificationLog $notification): NotificationLog
    {
        $id = $notification->id;

        DB::transaction(function () use ($actor, $id): void {
            $locked = NotificationLog::query()->lockForUpdate()->findOrFail($id);

            if ($locked->status !== NotificationStatus::Failed) {
                throw ValidationException::withMessages([
                    'notification' => 'Hanya notifikasi FAILED yang dapat di-retry.',
                ]);
            }

            $old = [
                'status' => $locked->status->value,
                'failed_at' => $locked->failed_at?->toIso8601String(),
                'error_message' => $locked->error_message,
            ];

            $locked->forceFill([
                'status' => NotificationStatus::Queued,
                'scheduled_at' => now(),
                'failed_at' => null,
                'error_message' => null,
                'provider_message_id' => null,
            ])->save();

            $this->auditLogger->log(
                actor: $actor,
                module: 'notification',
                action: 'RETRY',
                entity: $locked,
                oldValues: $old,
                newValues: ['status' => 'QUEUED'],
                metadata: [
                    'retry_count' => $locked->retry_count,
                    'channel' => $locked->channel->value,
                    'type' => $locked->type->value,
                ]
            );
        }, 3);

        SendWhatsAppNotificationJob::dispatch($id);

        return NotificationLog::query()->findOrFail($id);
    }
}

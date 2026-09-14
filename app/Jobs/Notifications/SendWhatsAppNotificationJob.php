<?php

namespace App\Jobs\Notifications;

use App\Contracts\WhatsApp\WhatsAppProvider;
use App\Enums\Communication\NotificationStatus;
use App\Models\Communication\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendWhatsAppNotificationJob implements
    ShouldQueue,
    ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $notificationId
    ) {
        $this->onQueue(
            (string) config(
                'school-notifications.queue',
                'notifications'
            )
        );
    }

    public function uniqueId(): string
    {
        return (string) $this->notificationId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(
        WhatsAppProvider $provider
    ): void {
        $notification = NotificationLog::query()
            ->findOrFail($this->notificationId);

        if (
            in_array(
                $notification->status,
                [
                    NotificationStatus::Sent,
                    NotificationStatus::Cancelled,
                ],
                true
            )
        ) {
            return;
        }

        $notification->forceFill([
            'status' => NotificationStatus::Processing,
            'retry_count' => $notification->retry_count + 1,
            'failed_at' => null,
        ])->save();

        try {
            $result = $provider->sendText(
                $notification->recipient,
                $notification->message
            );
        } catch (Throwable $exception) {
            $notification->forceFill([
                /*
                 * The queue worker will retry this same job.
                 * Until attempts are exhausted it returns to QUEUED.
                 */
                'status' => NotificationStatus::Queued,
                'error_message' => mb_substr(
                    $exception->getMessage(),
                    0,
                    2000
                ),
            ])->save();

            throw $exception;
        }

        $notification->forceFill([
            'status' => NotificationStatus::Sent,
            'provider_message_id' =>
                $result->providerMessageId,
            'sent_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();
    }

    public function failed(
        ?Throwable $exception
    ): void {
        $notification = NotificationLog::query()
            ->find($this->notificationId);

        if (
            ! $notification
            || $notification->status
                === NotificationStatus::Sent
        ) {
            return;
        }

        $notification->forceFill([
            'status' => NotificationStatus::Failed,
            'failed_at' => now(),
            'error_message' => mb_substr(
                $exception?->getMessage()
                    ?? 'WhatsApp job gagal.',
                0,
                2000
            ),
        ])->save();
    }
}

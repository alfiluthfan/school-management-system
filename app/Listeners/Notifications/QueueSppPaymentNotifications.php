<?php

namespace App\Listeners\Notifications;

use App\Enums\Communication\NotificationStatus;
use App\Enums\Communication\NotificationType;
use App\Events\Spp\SppPaymentPosted;
use App\Jobs\Notifications\SendWhatsAppNotificationJob;
use App\Models\Finance\SppPayment;
use App\Services\Notifications\NotificationMessageFactory;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\ParentRecipientResolver;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class QueueSppPaymentNotifications implements
    ShouldQueueAfterCommit
{
    public int $tries = 3;

    public string $queue = 'notifications';

    public function __construct(
        private readonly ParentRecipientResolver $recipientResolver,
        private readonly NotificationMessageFactory $messageFactory,
        private readonly NotificationService $notificationService
    ) {
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 30, 60];
    }

    public function handle(
        SppPaymentPosted $event
    ): void {
        $payment = SppPayment::query()
            ->with([
                'bill.student.user',
                'bill.student.guardians.user',
            ])
            ->findOrFail($event->sppPaymentId);

        $student = $payment->bill->student;

        $recipients = $this->recipientResolver
            ->forStudent($student);

        if ($recipients->isEmpty()) {
            return;
        }

        $content = $this->messageFactory
            ->sppPaymentPosted($payment);

        foreach ($recipients as $recipient) {
            $notification = $this->notificationService
                ->queueWhatsApp(
                    recipientUser: $recipient,
                    student: $student,
                    type: NotificationType::SppPaid,
                    subject: $content['subject'],
                    message: $content['message'],
                    dedupeKey: sprintf(
                        'spp-paid:%s:%s',
                        $payment->uuid,
                        $recipient->uuid
                    )
                );

            if (
                $notification->status
                === NotificationStatus::Queued
            ) {
                SendWhatsAppNotificationJob::dispatch(
                    $notification->id
                );
            }
        }
    }
}

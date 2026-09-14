<?php

namespace App\Listeners\Notifications;

use App\Enums\Communication\NotificationStatus;
use App\Enums\Communication\NotificationType;
use App\Events\Attendance\StudentLateDetected;
use App\Jobs\Notifications\SendWhatsAppNotificationJob;
use App\Models\Attendance\StudentAttendance;
use App\Services\Notifications\NotificationMessageFactory;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\ParentRecipientResolver;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class QueueStudentLateNotifications implements
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
        StudentLateDetected $event
    ): void {
        $attendance = StudentAttendance::query()
            ->with([
                'student.user',
                'student.guardians.user',
            ])
            ->findOrFail($event->studentAttendanceId);

        $recipients = $this->recipientResolver
            ->forStudent($attendance->student);

        if ($recipients->isEmpty()) {
            return;
        }

        $content = $this->messageFactory->studentLate(
            $attendance
        );

        foreach ($recipients as $recipient) {
            $notification = $this->notificationService
                ->queueWhatsApp(
                    recipientUser: $recipient,
                    student: $attendance->student,
                    type: NotificationType::StudentLate,
                    subject: $content['subject'],
                    message: $content['message'],
                    dedupeKey: sprintf(
                        'student-late:%s:%s',
                        $attendance->uuid,
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

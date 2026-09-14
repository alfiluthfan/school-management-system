<?php

namespace App\Services\Notifications;

use App\Enums\Communication\NotificationChannel;
use App\Enums\Communication\NotificationStatus;
use App\Enums\Communication\NotificationType;
use App\Models\Academic\Student;
use App\Models\Auth\User;
use App\Models\Communication\NotificationLog;

final class NotificationService
{
    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNormalizer
    ) {
    }

    public function queueWhatsApp(
        User $recipientUser,
        Student $student,
        NotificationType $type,
        string $subject,
        string $message,
        string $dedupeKey
    ): NotificationLog {
        $recipient = $this->phoneNormalizer->normalize(
            (string) $recipientUser->phone
        );

        return NotificationLog::query()->firstOrCreate(
            [
                'dedupe_key' => $dedupeKey,
            ],
            [
                'recipient_user_id' => $recipientUser->id,
                'student_id' => $student->id,
                'type' => $type,
                'channel' => NotificationChannel::WhatsApp,
                'recipient' => $recipient,
                'subject' => $subject,
                'message' => $message,
                'provider_message_id' => null,
                'status' => NotificationStatus::Queued,
                'scheduled_at' => now(),
                'sent_at' => null,
                'failed_at' => null,
                'retry_count' => 0,
                'error_message' => null,
            ]
        );
    }
}

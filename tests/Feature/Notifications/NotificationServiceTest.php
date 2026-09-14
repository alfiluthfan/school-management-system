<?php

namespace Tests\Feature\Notifications;

use App\Enums\Communication\NotificationStatus;
use App\Enums\Communication\NotificationType;
use App\Services\Notifications\NotificationService;

class NotificationServiceTest extends NotificationTestCase
{
    public function test_queue_whatsapp_is_idempotent_by_dedupe_key(): void
    {
        [, $student] = $this->createApiStudent();
        [$parentUser] = $this->createApiParent();

        $parentUser->update([
            'phone' => '0812 3456 7890',
        ]);

        $service = app(NotificationService::class);

        $first = $service->queueWhatsApp(
            recipientUser: $parentUser->fresh(),
            student: $student,
            type: NotificationType::StudentLate,
            subject: 'Test Subject',
            message: 'Test Message',
            dedupeKey: 'same-event:same-recipient'
        );

        $second = $service->queueWhatsApp(
            recipientUser: $parentUser->fresh(),
            student: $student,
            type: NotificationType::StudentLate,
            subject: 'Test Subject',
            message: 'Test Message',
            dedupeKey: 'same-event:same-recipient'
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            NotificationStatus::Queued,
            $first->status
        );
        $this->assertSame(
            '6281234567890',
            $first->recipient
        );
        $this->assertDatabaseCount('notifications', 1);
    }
}

<?php

namespace Tests\Feature\Notifications;

use App\Contracts\WhatsApp\WhatsAppProvider;
use App\Enums\Communication\NotificationStatus;
use App\Enums\Communication\NotificationType;
use App\Jobs\Notifications\SendWhatsAppNotificationJob;
use App\Services\Notifications\NotificationService;
use RuntimeException;
use Tests\Fakes\FakeWhatsAppProvider;

class SendWhatsAppNotificationJobTest extends NotificationTestCase
{
    public function test_job_marks_notification_sent_on_success(): void
    {
        $notification = $this->makeQueuedNotification();

        $fake = new FakeWhatsAppProvider();

        $this->app->instance(
            WhatsAppProvider::class,
            $fake
        );

        $job = new SendWhatsAppNotificationJob(
            $notification->id
        );

        $this->app->call([$job, 'handle']);

        $notification = $notification->fresh();

        $this->assertSame(
            NotificationStatus::Sent,
            $notification->status
        );
        $this->assertSame(
            'fake-message-id',
            $notification->provider_message_id
        );
        $this->assertNotNull($notification->sent_at);
        $this->assertSame(1, $notification->retry_count);
        $this->assertCount(1, $fake->sent);
    }

    public function test_job_can_retry_after_transient_provider_failure(): void
    {
        $notification = $this->makeQueuedNotification();

        $fake = new FakeWhatsAppProvider();
        $fake->failuresRemaining = 1;

        $this->app->instance(
            WhatsAppProvider::class,
            $fake
        );

        $job = new SendWhatsAppNotificationJob(
            $notification->id
        );

        try {
            $this->app->call([$job, 'handle']);

            $this->fail(
                'First provider call should fail.'
            );
        } catch (RuntimeException) {
            $notification = $notification->fresh();

            $this->assertSame(
                NotificationStatus::Queued,
                $notification->status
            );
            $this->assertSame(
                1,
                $notification->retry_count
            );
            $this->assertNotNull(
                $notification->error_message
            );
        }

        /*
         * Simulate the queue worker's next attempt.
         */
        $this->app->call([$job, 'handle']);

        $notification = $notification->fresh();

        $this->assertSame(
            NotificationStatus::Sent,
            $notification->status
        );
        $this->assertSame(
            2,
            $notification->retry_count
        );
        $this->assertNull(
            $notification->error_message
        );
    }

    public function test_failed_hook_marks_final_failure(): void
    {
        $notification = $this->makeQueuedNotification();

        $job = new SendWhatsAppNotificationJob(
            $notification->id
        );

        $exception = new RuntimeException(
            'Provider permanently unavailable.'
        );

        $job->failed($exception);

        $notification = $notification->fresh();

        $this->assertSame(
            NotificationStatus::Failed,
            $notification->status
        );
        $this->assertNotNull($notification->failed_at);
        $this->assertSame(
            'Provider permanently unavailable.',
            $notification->error_message
        );
    }

    private function makeQueuedNotification()
    {
        [, $student] = $this->createApiStudent();
        [$parentUser] = $this->createApiParent();

        $parentUser->update([
            'phone' => '0812-1213-7281',
        ]);

        return app(NotificationService::class)
            ->queueWhatsApp(
                recipientUser: $parentUser->fresh(),
                student: $student,
                type: NotificationType::StudentLate,
                subject: 'Late',
                message: 'Student is late.',
                dedupeKey: uniqid(
                    'job-test:',
                    true
                )
            );
    }
}

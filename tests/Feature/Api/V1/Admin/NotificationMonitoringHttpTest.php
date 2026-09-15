<?php
namespace Tests\Feature\Api\V1\Admin;

use App\Enums\Communication\NotificationStatus;
use App\Enums\Communication\NotificationType;
use App\Jobs\Notifications\SendWhatsAppNotificationJob;
use App\Models\Communication\NotificationLog;
use App\Services\Notifications\NotificationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsHttpApiFixtures;
use Tests\TestCase;

class NotificationMonitoringHttpTest extends TestCase
{
    use BuildsHttpApiFixtures;
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'school-notifications.whatsapp.default_country_calling_code' => '62',
        ]);

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    public function test_guest_gets_401_for_notification_monitoring(): void
    {
        $this->getJson(route('api.v1.admin.notifications.index'))
            ->assertUnauthorized();
    }

    public function test_student_gets_403_for_notification_monitoring(): void
    {
        [$user] = $this->createApiStudent();

        $this->actingAs($user)
            ->getJson(route('api.v1.admin.notifications.index'))
            ->assertForbidden();
    }

    public function test_admin_can_list_notification_logs(): void
    {
        $admin = $this->createApiAdmin();
        $notification = $this->makeNotification(
            NotificationStatus::Sent,
            NotificationType::StudentLate
        );

        $this->actingAs($admin)
            ->getJson(route('api.v1.admin.notifications.index'))
            ->assertOk()
            ->assertJsonFragment(['uuid' => $notification->uuid])
            ->assertJsonPath('data.0.status.value', 'SENT');
    }

    public function test_index_can_filter_failed_notifications(): void
    {
        $admin = $this->createApiAdmin();
        $failed = $this->makeNotification(
            NotificationStatus::Failed,
            NotificationType::SppPaid
        );
        $sent = $this->makeNotification(
            NotificationStatus::Sent,
            NotificationType::StudentLate
        );

        $this->actingAs($admin)
            ->getJson(route('api.v1.admin.notifications.index', ['status'=>'FAILED']))
            ->assertOk()
            ->assertJsonFragment(['uuid'=>$failed->uuid])
            ->assertJsonMissing(['uuid'=>$sent->uuid]);
    }

    public function test_invalid_filter_returns_422(): void
    {
        $admin = $this->createApiAdmin();

        $this->actingAs($admin)
            ->getJson(route('api.v1.admin.notifications.index', ['status'=>'UNKNOWN']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_admin_can_view_notification_detail(): void
    {
        $admin = $this->createApiAdmin();
        $notification = $this->makeNotification(
            NotificationStatus::Failed,
            NotificationType::SppOverdue
        );

        $this->actingAs($admin)
            ->getJson(route('api.v1.admin.notifications.show', $notification))
            ->assertOk()
            ->assertJsonPath('data.uuid', $notification->uuid)
            ->assertJsonPath('data.status.value', 'FAILED');
    }

    public function test_unknown_notification_uuid_returns_404(): void
    {
        $admin = $this->createApiAdmin();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/notifications/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    public function test_admin_can_read_notification_stats(): void
    {
        $admin = $this->createApiAdmin();

        $this->makeNotification(NotificationStatus::Sent, NotificationType::StudentLate);
        $this->makeNotification(NotificationStatus::Sent, NotificationType::SppPaid);
        $this->makeNotification(NotificationStatus::Failed, NotificationType::SppOverdue);
        $this->makeNotification(NotificationStatus::Queued, NotificationType::SppOverdue);

        $this->actingAs($admin)
            ->getJson(route('api.v1.admin.notifications.stats'))
            ->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.sent', 2)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.queued', 1)
            ->assertJsonPath('data.delivery_rate_percent', 66.67);
    }

    public function test_admin_can_retry_failed_notification(): void
    {
        Queue::fake([SendWhatsAppNotificationJob::class]);

        $admin = $this->createApiAdmin();
        $notification = $this->makeNotification(
            NotificationStatus::Failed,
            NotificationType::SppOverdue
        );

        $notification->forceFill([
            'failed_at' => now(),
            'error_message' => 'Provider unavailable.',
            'retry_count' => 3,
        ])->save();

        $this->actingAs($admin)
            ->postJson(route('api.v1.admin.notifications.retry', $notification))
            ->assertOk()
            ->assertJsonPath('data.status.value', 'QUEUED')
            ->assertJsonPath('data.retry_count', 3)
            ->assertJsonPath('data.error_message', null);

        Queue::assertPushed(
            SendWhatsAppNotificationJob::class,
            fn (SendWhatsAppNotificationJob $job): bool =>
                $job->notificationId === $notification->id
        );

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'notification',
            'action' => 'RETRY',
            'entity_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_retry_non_failed_notification_returns_422(): void
    {
        Queue::fake();

        $admin = $this->createApiAdmin();
        $notification = $this->makeNotification(
            NotificationStatus::Sent,
            NotificationType::StudentLate
        );

        $this->actingAs($admin)
            ->postJson(route('api.v1.admin.notifications.retry', $notification))
            ->assertUnprocessable();

        Queue::assertNothingPushed();
    }

    private function makeNotification(
        NotificationStatus $status,
        NotificationType $type
    ): NotificationLog {
        [, $student] = $this->createApiStudent();
        [$parentUser] = $this->createApiParent();

        $parentUser->update([
            'phone' => '0812'.random_int(10000000, 99999999),
        ]);

        $notification = app(NotificationService::class)->queueWhatsApp(
            recipientUser: $parentUser->fresh(),
            student: $student,
            type: $type,
            subject: 'Monitoring test',
            message: 'Monitoring message',
            dedupeKey: uniqid('monitoring:', true)
        );

        $notification->forceFill([
            'status' => $status,
            'sent_at' => $status === NotificationStatus::Sent ? now() : null,
            'failed_at' => $status === NotificationStatus::Failed ? now() : null,
            'error_message' => $status === NotificationStatus::Failed ? 'Test failure' : null,
        ])->save();

        return $notification->fresh();
    }
}

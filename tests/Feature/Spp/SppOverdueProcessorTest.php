<?php

namespace Tests\Feature\Spp;

use App\Enums\Finance\SppBillStatus;
use App\Jobs\Notifications\SendWhatsAppNotificationJob;
use App\Services\Spp\SppOverdueProcessor;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsHttpApiFixtures;
use Tests\TestCase;

class SppOverdueProcessorTest extends TestCase
{
    use BuildsHttpApiFixtures;
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Asia/Jakarta',
            'school-notifications.spp_overdue'
            .'.first_reminder_after_days' => 1,
            'school-notifications.spp_overdue'
            .'.reminder_interval_days' => 3,
            'school-notifications.spp_overdue'
            .'.chunk_size' => 50,
            'school-notifications.whatsapp'
            .'.default_country_calling_code' => '62',
        ]);

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    public function test_due_bill_is_marked_overdue_and_notification_is_queued(): void
    {
        Queue::fake([
            SendWhatsAppNotificationJob::class,
        ]);

        [, $student] = $this->createApiStudent();
        [$parentUser, $guardian] = $this->createApiParent();

        $parentUser->update([
            'phone' => '0812-1234-5678',
        ]);

        $this->linkApiParentToStudent(
            $guardian,
            $student
        );

        $year = $this->createApiAcademicYear();
        $bill = $this->createApiSppBill(
            $student,
            $year
        );

        $bill->update([
            'status' => SppBillStatus::Pending,
            'due_date' => '2026-09-10',
        ]);

        $result = app(
            SppOverdueProcessor::class
        )->run(
            CarbonImmutable::parse(
                '2026-09-11',
                'Asia/Jakarta'
            )
        );

        $bill = $bill->fresh();

        $this->assertSame(
            SppBillStatus::Overdue,
            $bill->status
        );

        $this->assertSame(1, $result->markedOverdue);
        $this->assertSame(1, $result->remindersCreated);
        $this->assertSame(
            1,
            $result->deliveryJobsDispatched
        );

        $notification = $student->notifications()
            ->where('type', 'SPP_OVERDUE')
            ->firstOrFail();

        $this->assertStringContainsString(
            'Pengingat Tunggakan SPP',
            $notification->subject
        );

        Queue::assertPushed(
            SendWhatsAppNotificationJob::class,
            fn (SendWhatsAppNotificationJob $job): bool =>
                $job->notificationId === $notification->id
        );

        $this->assertDatabaseHas('audit_logs', [
            'entity_id' => $bill->id,
            'module' => 'spp',
            'action' => 'MARK_OVERDUE',
        ]);
    }

    public function test_same_day_rerun_does_not_create_duplicate_reminder(): void
    {
        Queue::fake([
            SendWhatsAppNotificationJob::class,
        ]);

        [, $student] = $this->createApiStudent();
        [$parentUser, $guardian] = $this->createApiParent();

        $parentUser->update([
            'phone' => '0812-9999-8888',
        ]);

        $this->linkApiParentToStudent(
            $guardian,
            $student
        );

        $year = $this->createApiAcademicYear();
        $bill = $this->createApiSppBill(
            $student,
            $year
        );

        $bill->update([
            'status' => SppBillStatus::Pending,
            'due_date' => '2026-09-10',
        ]);

        $processor = app(SppOverdueProcessor::class);
        $date = CarbonImmutable::parse(
            '2026-09-11',
            'Asia/Jakarta'
        );

        $first = $processor->run($date);
        $second = $processor->run($date);

        $this->assertSame(1, $first->remindersCreated);
        $this->assertSame(0, $second->remindersCreated);

        $this->assertSame(
            1,
            $student->notifications()
                ->where('type', 'SPP_OVERDUE')
                ->count()
        );

        Queue::assertPushed(
            SendWhatsAppNotificationJob::class,
            1
        );
    }

    public function test_reminder_cadence_skips_non_matching_day(): void
    {
        Queue::fake([
            SendWhatsAppNotificationJob::class,
        ]);

        [, $student] = $this->createApiStudent();
        [$parentUser, $guardian] = $this->createApiParent();

        $parentUser->update([
            'phone' => '0812-7777-6666',
        ]);

        $this->linkApiParentToStudent(
            $guardian,
            $student
        );

        $year = $this->createApiAcademicYear();
        $bill = $this->createApiSppBill(
            $student,
            $year
        );

        $bill->update([
            'status' => SppBillStatus::Pending,
            'due_date' => '2026-09-10',
        ]);

        /*
         * Day 2 overdue. Default cadence is day 1,4,7,...
         */
        $result = app(
            SppOverdueProcessor::class
        )->run(
            CarbonImmutable::parse(
                '2026-09-12',
                'Asia/Jakarta'
            )
        );

        $this->assertSame(
            SppBillStatus::Overdue,
            $bill->fresh()->status
        );
        $this->assertSame(1, $result->markedOverdue);
        $this->assertSame(0, $result->remindersCreated);
        $this->assertSame(1, $result->skippedByCadence);

        Queue::assertNothingPushed();
    }

    public function test_paid_cancelled_and_not_yet_due_bills_are_ignored(): void
    {
        Queue::fake();

        [, $student] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();

        $paid = $this->createApiSppBill(
            $student,
            $year,
            '500000.00',
            8
        );
        $paid->update([
            'status' => SppBillStatus::Paid,
            'paid_amount' => '500000.00',
            'due_date' => '2026-09-01',
        ]);

        $cancelled = $this->createApiSppBill(
            $student,
            $year,
            '500000.00',
            9
        );
        $cancelled->update([
            'status' => SppBillStatus::Cancelled,
            'due_date' => '2026-09-01',
        ]);

        $future = $this->createApiSppBill(
            $student,
            $year,
            '500000.00',
            10
        );
        $future->update([
            'status' => SppBillStatus::Pending,
            'due_date' => '2026-09-20',
        ]);

        $result = app(
            SppOverdueProcessor::class
        )->run(
            CarbonImmutable::parse(
                '2026-09-14',
                'Asia/Jakarta'
            )
        );

        $this->assertSame(0, $result->scanned);
        $this->assertSame(
            SppBillStatus::Paid,
            $paid->fresh()->status
        );
        $this->assertSame(
            SppBillStatus::Cancelled,
            $cancelled->fresh()->status
        );
        $this->assertSame(
            SppBillStatus::Pending,
            $future->fresh()->status
        );
    }

    public function test_overdue_bill_without_recipient_is_still_marked_overdue(): void
    {
        Queue::fake();

        [, $student] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();

        $bill = $this->createApiSppBill(
            $student,
            $year
        );

        $bill->update([
            'status' => SppBillStatus::Pending,
            'due_date' => '2026-09-10',
        ]);

        $result = app(
            SppOverdueProcessor::class
        )->run(
            CarbonImmutable::parse(
                '2026-09-11',
                'Asia/Jakarta'
            )
        );

        $this->assertSame(
            SppBillStatus::Overdue,
            $bill->fresh()->status
        );
        $this->assertSame(1, $result->withoutRecipients);
        $this->assertSame(0, $result->remindersCreated);

        Queue::assertNothingPushed();
    }

    public function test_force_reminder_bypasses_cadence(): void
    {
        Queue::fake([
            SendWhatsAppNotificationJob::class,
        ]);

        [, $student] = $this->createApiStudent();
        [$parentUser, $guardian] = $this->createApiParent();

        $parentUser->update([
            'phone' => '0812-5555-4444',
        ]);

        $this->linkApiParentToStudent(
            $guardian,
            $student
        );

        $year = $this->createApiAcademicYear();
        $bill = $this->createApiSppBill(
            $student,
            $year
        );

        $bill->update([
            'status' => SppBillStatus::Pending,
            'due_date' => '2026-09-10',
        ]);

        $result = app(
            SppOverdueProcessor::class
        )->run(
            asOf: CarbonImmutable::parse(
                '2026-09-12',
                'Asia/Jakarta'
            ),
            forceReminder: true
        );

        $this->assertSame(1, $result->remindersCreated);
        Queue::assertPushed(
            SendWhatsAppNotificationJob::class,
            1
        );
    }
}

<?php

namespace Tests\Feature\Notifications;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\Communication\NotificationType;
use App\Enums\Finance\PaymentMethod;
use App\Enums\Finance\SppPaymentStatus;
use App\Events\Attendance\StudentLateDetected;
use App\Events\Spp\SppPaymentPosted;
use App\Jobs\Notifications\SendWhatsAppNotificationJob;
use App\Listeners\Notifications\QueueSppPaymentNotifications;
use App\Listeners\Notifications\QueueStudentLateNotifications;
use App\Models\Finance\SppPayment;
use Illuminate\Support\Facades\Queue;

class NotificationListenerTest extends NotificationTestCase
{
    public function test_late_listener_creates_log_and_queues_delivery_job(): void
    {
        Queue::fake([
            SendWhatsAppNotificationJob::class,
        ]);

        [, $student] = $this->createApiStudent();
        [$parentUser, $guardian] =
            $this->createApiParent();

        $parentUser->update([
            'phone' => '0812-0000-1111',
        ]);

        $this->linkApiParentToStudent(
            $guardian,
            $student
        );

        $year = $this->createApiAcademicYear();
        $class = $this->createApiClass($year);
        $attendance = $this->createApiAttendance(
            $student,
            $class
        );

        $attendance->update([
            'status' => AttendanceStatus::Late,
            'late_minutes' => 12,
        ]);

        app(
            QueueStudentLateNotifications::class
        )->handle(
            new StudentLateDetected($attendance->id)
        );

        $notification = $student->notifications()
            ->where(
                'type',
                NotificationType::StudentLate->value
            )
            ->firstOrFail();

        $this->assertSame(
            '6281200001111',
            $notification->recipient
        );

        Queue::assertPushed(
            SendWhatsAppNotificationJob::class,
            fn (
                SendWhatsAppNotificationJob $job
            ): bool =>
                $job->notificationId
                === $notification->id
        );

        /*
         * Re-running the listener does not create a duplicate row.
         */
        app(
            QueueStudentLateNotifications::class
        )->handle(
            new StudentLateDetected($attendance->id)
        );

        $this->assertSame(
            1,
            $student->notifications()
                ->where(
                    'type',
                    NotificationType::StudentLate->value
                )
                ->count()
        );
    }

    public function test_spp_listener_creates_confirmation_notification(): void
    {
        Queue::fake([
            SendWhatsAppNotificationJob::class,
        ]);

        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        [$parentUser, $guardian] =
            $this->createApiParent();

        $parentUser->update([
            'phone' => '0813-2222-3333',
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

        $payment = SppPayment::query()->create([
            'payment_number' => 'PAY-HTTP-NOTIF',
            'receipt_number' => 'RCT-HTTP-NOTIF',
            'spp_bill_id' => $bill->id,
            'created_by' => $admin->id,
            'amount' => '200000.00',
            'payment_method' => PaymentMethod::Cash,
            'reference_number' => null,
            'payment_date' => now(),
            'status' => SppPaymentStatus::Posted,
            'notes' => null,
        ]);

        app(
            QueueSppPaymentNotifications::class
        )->handle(
            new SppPaymentPosted($payment->id)
        );

        $notification = $student->notifications()
            ->where(
                'type',
                NotificationType::SppPaid->value
            )
            ->firstOrFail();

        $this->assertStringContainsString(
            'RCT-HTTP-NOTIF',
            $notification->message
        );

        Queue::assertPushed(
            SendWhatsAppNotificationJob::class,
            fn (
                SendWhatsAppNotificationJob $job
            ): bool =>
                $job->notificationId
                === $notification->id
        );
    }
}

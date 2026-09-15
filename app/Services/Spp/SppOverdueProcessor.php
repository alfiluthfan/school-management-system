<?php

namespace App\Services\Spp;

use App\Data\Spp\SppOverdueRunResult;
use App\Enums\Communication\NotificationType;
use App\Enums\Finance\SppBillStatus;
use App\Jobs\Notifications\SendWhatsAppNotificationJob;
use App\Models\Finance\SppBill;
use App\Services\Notifications\NotificationMessageFactory;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\ParentRecipientResolver;
use App\Services\System\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SppOverdueProcessor
{
    public function __construct(
        private readonly ParentRecipientResolver $recipientResolver,
        private readonly NotificationMessageFactory $messageFactory,
        private readonly NotificationService $notificationService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function run(
        ?CarbonImmutable $asOf = null,
        bool $forceReminder = false
    ): SppOverdueRunResult {
        $asOf ??= CarbonImmutable::now(
            config('app.timezone')
        );

        $date = $asOf->startOfDay();

        $chunkSize = max(
            1,
            (int) config(
                'school-notifications.spp_overdue.chunk_size',
                200
            )
        );

        $stats = [
            'scanned' => 0,
            'markedOverdue' => 0,
            'remindersCreated' => 0,
            'deliveryJobsDispatched' => 0,
            'withoutRecipients' => 0,
            'skippedByCadence' => 0,
        ];

        SppBill::query()
            ->whereDate('due_date', '<', $date->toDateString())
            ->whereNotIn('status', [
                SppBillStatus::Paid->value,
                SppBillStatus::Cancelled->value,
            ])
            ->orderBy('id')
            ->chunkById(
                $chunkSize,
                function ($bills) use (
                    $date,
                    $forceReminder,
                    &$stats
                ): void {
                    foreach ($bills as $bill) {
                        $stats['scanned']++;

                        $this->processBill(
                            bill: $bill,
                            asOfDate: $date,
                            forceReminder: $forceReminder,
                            stats: $stats
                        );
                    }
                }
            );

        return new SppOverdueRunResult(
            scanned: $stats['scanned'],
            markedOverdue: $stats['markedOverdue'],
            remindersCreated: $stats['remindersCreated'],
            deliveryJobsDispatched:
                $stats['deliveryJobsDispatched'],
            withoutRecipients: $stats['withoutRecipients'],
            skippedByCadence: $stats['skippedByCadence'],
        );
    }

    /**
     * @param array<string, int> $stats
     */
    private function processBill(
        SppBill $bill,
        CarbonImmutable $asOfDate,
        bool $forceReminder,
        array &$stats
    ): void {
        $dueDate = CarbonImmutable::parse(
            $bill->due_date->toDateString(),
            config('app.timezone')
        )->startOfDay();

        $overdueDays = $dueDate->diffInDays(
            $asOfDate,
            false
        );

        if ($overdueDays <= 0) {
            return;
        }

        $shouldRemind = $forceReminder
            || $this->shouldSendReminder($overdueDays);

        $student = $bill->student()->with([
            'user',
            'guardians.user',
        ])->firstOrFail();

        $recipients = $shouldRemind
            ? $this->recipientResolver->forStudent($student)
            : collect();

        if (! $shouldRemind) {
            $stats['skippedByCadence']++;
        } elseif ($recipients->isEmpty()) {
            $stats['withoutRecipients']++;
        }

        $newNotificationIds = [];

        DB::transaction(function () use (
            $bill,
            $student,
            $recipients,
            $asOfDate,
            $overdueDays,
            $shouldRemind,
            &$newNotificationIds,
            &$stats
        ): void {
            $lockedBill = SppBill::query()
                ->lockForUpdate()
                ->findOrFail($bill->id);

            if (
                in_array(
                    $lockedBill->status,
                    [
                        SppBillStatus::Paid,
                        SppBillStatus::Cancelled,
                    ],
                    true
                )
            ) {
                return;
            }

            if ($lockedBill->status !== SppBillStatus::Overdue) {
                $oldStatus = $lockedBill->status;

                $lockedBill->update([
                    'status' => SppBillStatus::Overdue,
                ]);

                $this->auditLogger->log(
                    actor: null,
                    module: 'spp',
                    action: 'MARK_OVERDUE',
                    entity: $lockedBill,
                    oldValues: [
                        'status' => $oldStatus->value,
                    ],
                    newValues: [
                        'status' =>
                            SppBillStatus::Overdue->value,
                    ],
                    metadata: [
                        'due_date' =>
                            $lockedBill->due_date
                                ->toDateString(),
                        'overdue_days' => $overdueDays,
                        'as_of_date' =>
                            $asOfDate->toDateString(),
                    ]
                );

                $stats['markedOverdue']++;
            }

            if (! $shouldRemind || $recipients->isEmpty()) {
                return;
            }

            $content = $this->messageFactory->sppOverdue(
                $lockedBill,
                $asOfDate
            );

            foreach ($recipients as $recipient) {
                $notification =
                    $this->notificationService
                        ->queueWhatsApp(
                            recipientUser: $recipient,
                            student: $student,
                            type: NotificationType::SppOverdue,
                            subject: $content['subject'],
                            message: $content['message'],
                            dedupeKey: sprintf(
                                'spp-overdue:%s:%s:%s',
                                $lockedBill->uuid,
                                $recipient->uuid,
                                $asOfDate->toDateString()
                            )
                        );

                if ($notification->wasRecentlyCreated) {
                    $newNotificationIds[] =
                        $notification->id;
                    $stats['remindersCreated']++;
                }
            }
        }, 3);

        foreach ($newNotificationIds as $notificationId) {
            SendWhatsAppNotificationJob::dispatch(
                $notificationId
            )->afterCommit();

            $stats['deliveryJobsDispatched']++;
        }
    }

    private function shouldSendReminder(
        int $overdueDays
    ): bool {
        $firstReminderAfterDays = max(
            1,
            (int) config(
                'school-notifications.spp_overdue'
                .'.first_reminder_after_days',
                1
            )
        );

        $intervalDays = max(
            1,
            (int) config(
                'school-notifications.spp_overdue'
                .'.reminder_interval_days',
                3
            )
        );

        if ($overdueDays < $firstReminderAfterDays) {
            return false;
        }

        return (
            ($overdueDays - $firstReminderAfterDays)
            % $intervalDays
        ) === 0;
    }
}

<?php

namespace App\Queries\Reports;

use App\Authorization\SchoolDataScope;
use App\Models\Auth\User;
use Carbon\CarbonImmutable;

final class SppReportQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(
        User $user,
        CarbonImmutable $from,
        CarbonImmutable $to
    ): array {
        /*
         * Bill cohort is defined by due_date within the requested range.
         * Collection flow is defined independently by payment_date.
         */
        $bills = SchoolDataScope::sppBills($user)
            ->whereBetween(
                'due_date',
                [
                    $from->toDateString(),
                    $to->toDateString(),
                ]
            );

        $payments =
            SchoolDataScope::sppPayments($user)
            ->where(
                'status',
                'POSTED'
            )
            ->whereBetween(
                'payment_date',
                [
                    $from,
                    $to,
                ]
            );

        $billStatus = (clone $bills)
            ->selectRaw(
                'status, COUNT(*) AS aggregate'
            )
            ->groupBy('status')
            ->pluck(
                'aggregate',
                'status'
            );

        $billed = (clone $bills)
            ->where(
                'status',
                '!=',
                'CANCELLED'
            )
            ->sum('amount');

        $paidOnCohort = (clone $bills)
            ->where(
                'status',
                '!=',
                'CANCELLED'
            )
            ->sum('paid_amount');

        $outstanding = max(
            0,
            (float) $billed
            - (float) $paidOnCohort
        );

        $collections = (clone $payments)
            ->selectRaw(
                'DATE(payment_date) AS payment_day'
            )
            ->selectRaw(
                'COUNT(*) AS payment_count'
            )
            ->selectRaw(
                'SUM(amount) AS total_amount'
            )
            ->groupBy(
                'payment_day'
            )
            ->orderBy(
                'payment_day'
            )
            ->get()
            ->map(
                fn ($row): array => [
                    'date' =>
                        (string) $row
                            ->payment_day,
                    'payment_count' =>
                        (int) $row
                            ->payment_count,
                    'amount' =>
                        $this->money(
                            $row
                                ->total_amount
                        ),
                ]
            )
            ->values()
            ->all();

        $collectionRatio =
            (float) $billed > 0
                ? round(
                    min(
                        100,
                        (
                            (float) $paidOnCohort
                            / (float) $billed
                        ) * 100
                    ),
                    2
                )
                : 0.0;

        return [
            'period' => [
                'from' =>
                    $from->toDateString(),
                'to' =>
                    $to->toDateString(),
            ],
            'bills' => [
                'count' =>
                    (clone $bills)
                        ->count(),
                'pending' => (int) (
                    $billStatus['PENDING']
                    ?? 0
                ),
                'partial' => (int) (
                    $billStatus['PARTIAL']
                    ?? 0
                ),
                'paid' => (int) (
                    $billStatus['PAID']
                    ?? 0
                ),
                'overdue' => (int) (
                    $billStatus['OVERDUE']
                    ?? 0
                ),
                'cancelled' => (int) (
                    $billStatus['CANCELLED']
                    ?? 0
                ),
                'billed_amount' =>
                    $this->money(
                        $billed
                    ),
                'paid_amount' =>
                    $this->money(
                        $paidOnCohort
                    ),
                'outstanding_amount' =>
                    $this->money(
                        $outstanding
                    ),
                'collection_ratio_percent' =>
                    $collectionRatio,
            ],
            'collections' => [
                'payment_count' =>
                    (clone $payments)
                        ->count(),
                'amount' =>
                    $this->money(
                        (clone $payments)
                            ->sum('amount')
                    ),
                'daily' =>
                    $collections,
            ],
        ];
    }

    private function money(
        mixed $value
    ): string {
        return number_format(
            (float) $value,
            2,
            '.',
            ''
        );
    }
}

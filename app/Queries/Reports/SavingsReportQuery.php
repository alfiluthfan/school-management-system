<?php

namespace App\Queries\Reports;

use App\Authorization\SchoolDataScope;
use App\Models\Auth\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class SavingsReportQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(
        User $user,
        CarbonImmutable $from,
        CarbonImmutable $to
    ): array {
        $transactions =
            SchoolDataScope::savingTransactions(
                $user
            )
            ->where(
                'status',
                'POSTED'
            )
            ->whereBetween(
                'transaction_date',
                [
                    $from,
                    $to,
                ]
            );

        $accounts =
            SchoolDataScope::savingAccounts(
                $user
            );

        $byType = (clone $transactions)
            ->selectRaw(
                'transaction_type, COUNT(*) AS transaction_count, SUM(amount) AS total_amount'
            )
            ->groupBy(
                'transaction_type'
            )
            ->get()
            ->mapWithKeys(
                fn($row): array => [
                    $row->transaction_type->value => [
                        'count' =>
                        (int) $row
                            ->transaction_count,
                        'amount' =>
                        $this->money(
                            $row
                                ->total_amount
                        ),
                    ],
                ]
            );

        $daily = (clone $transactions)
            ->selectRaw(
                'DATE(transaction_date) AS transaction_day'
            )
            ->selectRaw(
                'COUNT(*) AS transaction_count'
            )
            ->selectRaw(
                "SUM(CASE WHEN transaction_type = 'DEPOSIT' THEN amount ELSE 0 END) AS deposits"
            )
            ->selectRaw(
                "SUM(CASE WHEN transaction_type = 'WITHDRAWAL' THEN amount ELSE 0 END) AS withdrawals"
            )
            ->selectRaw(
                "SUM(CASE WHEN transaction_type = 'REVERSAL' THEN amount ELSE 0 END) AS reversals"
            )
            ->groupBy(
                'transaction_day'
            )
            ->orderBy(
                'transaction_day'
            )
            ->get()
            ->map(
                fn($row): array => [
                    'date' =>
                    (string) $row
                        ->transaction_day,
                    'transaction_count' =>
                    (int) $row
                        ->transaction_count,
                    'deposits' =>
                    $this->money(
                        $row->deposits
                    ),
                    'withdrawals' =>
                    $this->money(
                        $row->withdrawals
                    ),
                    'reversals' =>
                    $this->money(
                        $row->reversals
                    ),
                ]
            )
            ->values()
            ->all();

        return [
            'period' => [
                'from' =>
                $from->toDateString(),
                'to' =>
                $to->toDateString(),
            ],
            'snapshot' => [
                'accounts' => (clone $accounts)
                    ->count(),
                'total_balance' =>
                $this->money(
                    (clone $accounts)
                        ->sum(
                            'current_balance'
                        )
                ),
            ],
            'transactions' => [
                'count' => (clone $transactions)
                    ->count(),
                'deposit' =>
                $byType['DEPOSIT']
                    ?? [
                        'count' => 0,
                        'amount' => '0.00',
                    ],
                'withdrawal' =>
                $byType['WITHDRAWAL']
                    ?? [
                        'count' => 0,
                        'amount' => '0.00',
                    ],
                'reversal' =>
                $byType['REVERSAL']
                    ?? [
                        'count' => 0,
                        'amount' => '0.00',
                    ],
                'adjustment' =>
                $byType['ADJUSTMENT']
                    ?? [
                        'count' => 0,
                        'amount' => '0.00',
                    ],
            ],
            'daily' => $daily,
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

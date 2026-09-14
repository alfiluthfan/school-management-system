<?php

namespace App\Actions\Savings;

use App\Enums\Finance\SavingTransactionStatus;
use App\Enums\Finance\SavingTransactionType;
use App\Models\Auth\User;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SavingTransaction;
use App\Services\System\AuditLogger;
use App\Support\BusinessNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReverseSavingTransactionAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * Execute this Action only after the required approval has been granted.
     */
    public function execute(
        User $actor,
        SavingTransaction $original,
        string $reason
    ): SavingTransaction {
        return DB::transaction(function () use (
            $actor,
            $original,
            $reason
        ): SavingTransaction {
            $lockedOriginal = SavingTransaction::query()
                ->lockForUpdate()
                ->findOrFail($original->id);

            if (
                $lockedOriginal->status
                !== SavingTransactionStatus::Posted
            ) {
                throw ValidationException::withMessages([
                    'transaction' => 'Hanya transaksi POSTED yang dapat direversal.',
                ]);
            }

            if (
                ! in_array(
                    $lockedOriginal->transaction_type,
                    [
                        SavingTransactionType::Deposit,
                        SavingTransactionType::Withdrawal,
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'transaction' => 'Jenis transaksi ini belum mendukung reversal otomatis.',
                ]);
            }

            $alreadyReversed = SavingTransaction::query()
                ->where(
                    'reference_transaction_id',
                    $lockedOriginal->id
                )
                ->where(
                    'transaction_type',
                    SavingTransactionType::Reversal->value
                )
                ->where(
                    'status',
                    SavingTransactionStatus::Posted->value
                )
                ->exists();

            if ($alreadyReversed) {
                throw ValidationException::withMessages([
                    'transaction' => 'Transaksi sudah pernah direversal.',
                ]);
            }

            $account = SavingAccount::query()
                ->lockForUpdate()
                ->findOrFail($lockedOriginal->saving_account_id);

            $before = $account->current_balance;

            $after = match ($lockedOriginal->transaction_type) {
                SavingTransactionType::Deposit => bcsub(
                    $before,
                    $lockedOriginal->amount,
                    2
                ),
                SavingTransactionType::Withdrawal => bcadd(
                    $before,
                    $lockedOriginal->amount,
                    2
                ),
                default => $before,
            };

            if (bccomp($after, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'transaction' => 'Reversal akan membuat saldo menjadi negatif.',
                ]);
            }

            $reversal = SavingTransaction::query()->create([
                'transaction_number' => BusinessNumber::savingTransaction(),
                'saving_account_id' => $account->id,
                'created_by' => $actor->id,
                'reference_transaction_id' => $lockedOriginal->id,
                'transaction_type' => SavingTransactionType::Reversal,
                'amount' => $lockedOriginal->amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $reason,
                'status' => SavingTransactionStatus::Posted,
                'transaction_date' => now(),
            ]);

            $lockedOriginal->update([
                'status' => SavingTransactionStatus::Reversed,
            ]);

            $account->update([
                'current_balance' => $after,
            ]);

            $this->auditLogger->log(
                $actor,
                'saving',
                'REVERSAL',
                $reversal,
                null,
                $reversal->getAttributes(),
                ['original_transaction_id' => $lockedOriginal->id]
            );

            return $reversal;
        });
    }
}

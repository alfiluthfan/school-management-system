<?php

namespace App\Actions\Savings;

use App\Enums\Finance\SavingAccountStatus;
use App\Enums\Finance\SavingTransactionStatus;
use App\Enums\Finance\SavingTransactionType;
use App\Models\Auth\User;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SavingTransaction;
use App\Services\System\AuditLogger;
use App\Support\BusinessNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WithdrawSavingAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function execute(
        User $actor,
        SavingAccount $account,
        string $amount,
        ?string $description = null
    ): SavingTransaction {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use (
            $actor,
            $account,
            $amount,
            $description
        ): SavingTransaction {
            $lockedAccount = SavingAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);

            if ($lockedAccount->status !== SavingAccountStatus::Active) {
                throw ValidationException::withMessages([
                    'account' => 'Rekening tabungan tidak aktif.',
                ]);
            }

            if (
                bccomp(
                    $lockedAccount->current_balance,
                    $amount,
                    2
                ) < 0
            ) {
                throw ValidationException::withMessages([
                    'amount' => 'Saldo tabungan tidak mencukupi.',
                ]);
            }

            $before = $lockedAccount->current_balance;
            $after = bcsub($before, $amount, 2);

            $transaction = SavingTransaction::query()->create([
                'transaction_number' => BusinessNumber::savingTransaction(),
                'saving_account_id' => $lockedAccount->id,
                'created_by' => $actor->id,
                'reference_transaction_id' => null,
                'transaction_type' => SavingTransactionType::Withdrawal,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $description,
                'status' => SavingTransactionStatus::Posted,
                'transaction_date' => now(),
            ]);

            $lockedAccount->update([
                'current_balance' => $after,
            ]);

            $this->auditLogger->log(
                $actor,
                'saving',
                'WITHDRAWAL',
                $transaction,
                null,
                $transaction->getAttributes(),
                ['saving_account_id' => $lockedAccount->id]
            );

            return $transaction;
        });
    }

    private function assertPositiveAmount(string $amount): void
    {
        if (! is_numeric($amount) || bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal penarikan harus lebih besar dari 0.',
            ]);
        }
    }
}

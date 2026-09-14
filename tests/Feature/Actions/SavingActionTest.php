<?php

namespace Tests\Feature\Actions;

use App\Actions\Savings\DepositSavingAction;
use App\Actions\Savings\ReverseSavingTransactionAction;
use App\Actions\Savings\WithdrawSavingAction;
use App\Enums\Finance\SavingTransactionStatus;
use App\Enums\Finance\SavingTransactionType;
use Illuminate\Validation\ValidationException;

class SavingActionTest extends ActionTestCase
{
    public function test_deposit_creates_ledger_and_updates_cached_balance(): void
    {
        $actor = $this->createActor('admin');
        [, $student] = $this->createStudentProfile();
        $account = $this->createSavingAccount(
            $student,
            '100000.00'
        );

        $transaction = app(DepositSavingAction::class)->execute(
            actor: $actor,
            account: $account,
            amount: '50000.00',
            description: 'Setoran test'
        );

        $this->assertSame(
            SavingTransactionType::Deposit,
            $transaction->transaction_type
        );
        $this->assertSame('100000.00', $transaction->balance_before);
        $this->assertSame('150000.00', $transaction->balance_after);

        $this->assertSame(
            '150000.00',
            $account->fresh()->current_balance
        );

        $this->assertDatabaseHas('audit_logs', [
            'entity_id' => $transaction->id,
            'module' => 'saving',
            'action' => 'DEPOSIT',
        ]);
    }

    public function test_withdrawal_creates_ledger_and_reduces_balance(): void
    {
        $actor = $this->createActor('admin');
        [, $student] = $this->createStudentProfile();
        $account = $this->createSavingAccount(
            $student,
            '100000.00'
        );

        $transaction = app(WithdrawSavingAction::class)->execute(
            actor: $actor,
            account: $account,
            amount: '40000.00'
        );

        $this->assertSame(
            SavingTransactionType::Withdrawal,
            $transaction->transaction_type
        );
        $this->assertSame(
            '60000.00',
            $account->fresh()->current_balance
        );
    }

    public function test_withdrawal_rejects_insufficient_balance(): void
    {
        $actor = $this->createActor('admin');
        [, $student] = $this->createStudentProfile();
        $account = $this->createSavingAccount(
            $student,
            '100000.00'
        );

        $this->expectException(ValidationException::class);

        app(WithdrawSavingAction::class)->execute(
            actor: $actor,
            account: $account,
            amount: '100000.01'
        );
    }

    public function test_reversal_creates_new_ledger_row_and_restores_balance(): void
    {
        $actor = $this->createActor('admin');
        [, $student] = $this->createStudentProfile();
        $account = $this->createSavingAccount(
            $student,
            '100000.00'
        );

        $deposit = app(DepositSavingAction::class)->execute(
            actor: $actor,
            account: $account,
            amount: '50000.00'
        );

        $reversal = app(
            ReverseSavingTransactionAction::class
        )->execute(
            actor: $actor,
            original: $deposit,
            reason: 'Salah nominal'
        );

        $this->assertSame(
            SavingTransactionType::Reversal,
            $reversal->transaction_type
        );
        $this->assertSame($deposit->id, $reversal->reference_transaction_id);
        $this->assertSame(
            '100000.00',
            $account->fresh()->current_balance
        );

        $this->assertSame(
            SavingTransactionStatus::Reversed,
            $deposit->fresh()->status
        );
    }

    public function test_duplicate_reversal_is_rejected(): void
    {
        $actor = $this->createActor('admin');
        [, $student] = $this->createStudentProfile();
        $account = $this->createSavingAccount($student);

        $deposit = app(DepositSavingAction::class)->execute(
            actor: $actor,
            account: $account,
            amount: '50000.00'
        );

        $action = app(ReverseSavingTransactionAction::class);

        $action->execute(
            actor: $actor,
            original: $deposit,
            reason: 'Reversal pertama'
        );

        $this->expectException(ValidationException::class);

        $action->execute(
            actor: $actor,
            original: $deposit,
            reason: 'Reversal kedua'
        );
    }
}

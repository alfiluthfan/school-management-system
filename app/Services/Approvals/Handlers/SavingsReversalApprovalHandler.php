<?php

namespace App\Services\Approvals\Handlers;

use App\Actions\Savings\ReverseSavingTransactionAction;
use App\Contracts\Approvals\ApprovalHandler;
use App\Enums\Finance\SavingTransactionStatus;
use App\Enums\Finance\SavingTransactionType;
use App\Models\Auth\User;
use App\Models\Finance\SavingTransaction;
use App\Models\System\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class SavingsReversalApprovalHandler implements ApprovalHandler
{
    public function __construct(
        private readonly ReverseSavingTransactionAction $reverseAction
    ) {
    }

    public function validateSubmission(
        User $requester,
        Model $entity,
        array $payload
    ): void {
        if (! $entity instanceof SavingTransaction) {
            throw new InvalidArgumentException(
                'Savings reversal approval membutuhkan SavingTransaction.'
            );
        }

        $transaction = $entity->fresh();

        if (
            $transaction->status
            !== SavingTransactionStatus::Posted
        ) {
            throw ValidationException::withMessages([
                'transaction' =>
                    'Hanya transaksi POSTED yang dapat diajukan untuk reversal.',
            ]);
        }

        if (
            ! in_array(
                $transaction->transaction_type,
                [
                    SavingTransactionType::Deposit,
                    SavingTransactionType::Withdrawal,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'transaction' =>
                    'Jenis transaksi ini belum mendukung reversal.',
            ]);
        }

        $alreadyReversed = SavingTransaction::query()
            ->where(
                'reference_transaction_id',
                $transaction->id
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
                'transaction' =>
                    'Transaksi sudah pernah direversal.',
            ]);
        }
    }

    public function pendingKey(Model $entity): string
    {
        if (! $entity instanceof SavingTransaction) {
            throw new InvalidArgumentException(
                'Invalid savings reversal entity.'
            );
        }

        return sprintf(
            'SAVING:VOID:%s:%s',
            $entity->getMorphClass(),
            $entity->uuid
        );
    }

    public function execute(
        User $reviewer,
        Approval $approval
    ): array {
        $transaction = SavingTransaction::query()
            ->findOrFail($approval->entity_id);

        if (
            $approval->entity_type
            !== $transaction->getMorphClass()
        ) {
            throw new InvalidArgumentException(
                'Approval entity tidak cocok dengan SavingTransaction.'
            );
        }

        $reversal = $this->reverseAction->execute(
            actor: $reviewer,
            original: $transaction,
            reason: $approval->reason
        );

        return [
            'executed_entity_type' =>
                $reversal->getMorphClass(),
            'executed_entity_id' => $reversal->id,
            'executed_entity_uuid' => $reversal->uuid,
            'reversal_transaction_number' =>
                $reversal->transaction_number,
        ];
    }
}

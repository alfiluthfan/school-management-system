<?php

namespace App\Services\Approvals\Handlers;

use App\Actions\Spp\CorrectSppPaymentAction;
use App\Contracts\Approvals\ApprovalHandler;
use App\Models\Auth\User;
use App\Models\Finance\SppPayment;
use App\Models\System\Approval;
use App\Services\Spp\SppPaymentCorrectionValidator;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class SppPaymentCorrectionApprovalHandler implements ApprovalHandler
{
    public function __construct(
        private readonly CorrectSppPaymentAction $correctionAction,
        private readonly SppPaymentCorrectionValidator $validator
    ) {
    }

    public function validateSubmission(User $requester, Model $entity, array $payload): void
    {
        if (! $entity instanceof SppPayment) {
            throw new InvalidArgumentException(
                'SPP correction approval membutuhkan SppPayment.'
            );
        }

        $changes = $payload['changes'] ?? [];

        $this->validator->resolve(
            $entity->fresh(['bill']),
            is_array($changes) ? $changes : []
        );
    }

    public function pendingKey(Model $entity): string
    {
        if (! $entity instanceof SppPayment) {
            throw new InvalidArgumentException('Invalid SPP payment entity.');
        }

        return sprintf(
            'SPP:PAYMENT:MUTATION:%s:%s',
            $entity->getMorphClass(),
            $entity->uuid
        );
    }

    public function execute(User $reviewer, Approval $approval): array
    {
        $payment = SppPayment::query()->findOrFail($approval->entity_id);

        if ($approval->entity_type !== $payment->getMorphClass()) {
            throw new InvalidArgumentException(
                'Approval entity tidak cocok dengan SppPayment.'
            );
        }

        $approval->loadMissing('requester');

        $replacement = $this->correctionAction->execute(
            requester: $approval->requester,
            reviewer: $reviewer,
            payment: $payment,
            changes: $approval->request_payload['changes'] ?? [],
            reason: $approval->reason
        );

        return [
            'original_payment_uuid' => $payment->uuid,
            'replacement_payment_uuid' => $replacement->uuid,
            'replacement_receipt_number' => $replacement->receipt_number,
            'replacement_amount' => $replacement->amount,
            'bill_uuid' => $replacement->bill->uuid,
            'bill_paid_amount' => $replacement->bill->paid_amount,
            'bill_status' => $replacement->bill->status->value,
        ];
    }
}

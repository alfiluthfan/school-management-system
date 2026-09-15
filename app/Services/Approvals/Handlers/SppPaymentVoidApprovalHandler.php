<?php

namespace App\Services\Approvals\Handlers;

use App\Actions\Spp\VoidSppPaymentAction;
use App\Contracts\Approvals\ApprovalHandler;
use App\Enums\Finance\SppPaymentStatus;
use App\Models\Auth\User;
use App\Models\Finance\SppPayment;
use App\Models\System\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class SppPaymentVoidApprovalHandler implements ApprovalHandler
{
    public function __construct(
        private readonly VoidSppPaymentAction $voidAction
    ) {
    }

    public function validateSubmission(User $requester, Model $entity, array $payload): void
    {
        if (! $entity instanceof SppPayment) {
            throw new InvalidArgumentException('SPP void approval membutuhkan SppPayment.');
        }

        if ($entity->status !== SppPaymentStatus::Posted) {
            throw ValidationException::withMessages([
                'payment' => 'Hanya pembayaran POSTED yang dapat diajukan untuk void.',
            ]);
        }
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

        $voided = $this->voidAction->execute(
            actor: $reviewer,
            payment: $payment,
            reason: $approval->reason
        );

        return [
            'spp_payment_uuid' => $voided->uuid,
            'spp_payment_status' => $voided->status->value,
            'bill_uuid' => $voided->bill->uuid,
            'bill_paid_amount' => $voided->bill->paid_amount,
            'bill_status' => $voided->bill->status->value,
        ];
    }
}

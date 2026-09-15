<?php

namespace App\Actions\Spp;

use App\Enums\Finance\SppPaymentStatus;
use App\Events\Spp\SppPaymentPosted;
use App\Models\Auth\User;
use App\Models\Finance\SppBill;
use App\Models\Finance\SppPayment;
use App\Services\Spp\SppBillPaymentStateService;
use App\Services\Spp\SppPaymentCorrectionValidator;
use App\Services\System\AuditLogger;
use App\Support\BusinessNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CorrectSppPaymentAction
{
    public function __construct(
        private readonly SppPaymentCorrectionValidator $validator,
        private readonly SppBillPaymentStateService $billState,
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function execute(
        User $requester,
        User $reviewer,
        SppPayment $payment,
        array $changes,
        string $reason
    ): SppPayment {
        return DB::transaction(function () use (
            $requester,
            $reviewer,
            $payment,
            $changes,
            $reason
        ): SppPayment {
            $lockedPayment = SppPayment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            $bill = SppBill::query()
                ->lockForUpdate()
                ->findOrFail($lockedPayment->spp_bill_id);

            $lockedPayment->setRelation('bill', $bill);
            $resolved = $this->validator->resolve($lockedPayment, $changes);

            if ($lockedPayment->status !== SppPaymentStatus::Posted) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran sudah tidak berstatus POSTED.',
                ]);
            }

            $originalSnapshot = [
                'payment_number' => $lockedPayment->payment_number,
                'receipt_number' => $lockedPayment->receipt_number,
                'amount' => $lockedPayment->amount,
                'payment_method' => $lockedPayment->payment_method->value,
                'reference_number' => $lockedPayment->reference_number,
                'notes' => $lockedPayment->notes,
                'status' => $lockedPayment->status->value,
            ];

            $oldBill = [
                'paid_amount' => $bill->paid_amount,
                'status' => $bill->status->value,
            ];

            $lockedPayment->forceFill([
                'status' => SppPaymentStatus::Void,
                'voided_by' => $reviewer->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            $replacement = SppPayment::query()->create([
                'payment_number' => BusinessNumber::sppPayment(),
                'receipt_number' => BusinessNumber::sppReceipt(),
                'spp_bill_id' => $bill->id,
                'replaces_payment_id' => $lockedPayment->id,
                'created_by' => $requester->id,
                'amount' => $resolved['amount'],
                'payment_method' => $resolved['payment_method'],
                'reference_number' => $resolved['reference_number'],
                'payment_date' => $lockedPayment->payment_date,
                'status' => SppPaymentStatus::Posted,
                'notes' => $resolved['notes'],
            ]);

            $bill = $this->billState->recalculate($bill);

            $this->auditLogger->log(
                actor: $reviewer,
                module: 'spp',
                action: 'PAYMENT_CORRECTED',
                entity: $replacement,
                oldValues: $originalSnapshot,
                newValues: [
                    'payment_number' => $replacement->payment_number,
                    'receipt_number' => $replacement->receipt_number,
                    'amount' => $replacement->amount,
                    'payment_method' => $replacement->payment_method->value,
                    'reference_number' => $replacement->reference_number,
                    'notes' => $replacement->notes,
                    'status' => $replacement->status->value,
                ],
                metadata: [
                    'requested_by' => $requester->id,
                    'reviewed_by' => $reviewer->id,
                    'original_payment_id' => $lockedPayment->id,
                    'original_payment_uuid' => $lockedPayment->uuid,
                    'replacement_payment_id' => $replacement->id,
                    'replacement_payment_uuid' => $replacement->uuid,
                    'reason' => $reason,
                    'bill_before' => $oldBill,
                    'bill_after' => [
                        'paid_amount' => $bill->paid_amount,
                        'status' => $bill->status->value,
                    ],
                ]
            );

            DB::afterCommit(
                fn () => event(new SppPaymentPosted($replacement->id))
            );

            return $replacement->fresh(['bill', 'creator', 'replacesPayment']);
        }, 3);
    }
}

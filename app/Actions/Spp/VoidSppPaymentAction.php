<?php

namespace App\Actions\Spp;

use App\Enums\Finance\SppBillStatus;
use App\Enums\Finance\SppPaymentStatus;
use App\Models\Auth\User;
use App\Models\Finance\SppBill;
use App\Models\Finance\SppPayment;
use App\Services\Spp\SppBillPaymentStateService;
use App\Services\System\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VoidSppPaymentAction
{
    public function __construct(
        private readonly SppBillPaymentStateService $billState,
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function execute(
        User $actor,
        SppPayment $payment,
        string $reason
    ): SppPayment {
        return DB::transaction(function () use ($actor, $payment, $reason): SppPayment {
            $lockedPayment = SppPayment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            $bill = SppBill::query()
                ->lockForUpdate()
                ->findOrFail($lockedPayment->spp_bill_id);

            if ($lockedPayment->status !== SppPaymentStatus::Posted) {
                throw ValidationException::withMessages([
                    'payment' => 'Hanya pembayaran POSTED yang dapat dibatalkan.',
                ]);
            }

            if ($bill->status === SppBillStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'bill' => 'Tagihan SPP yang dibatalkan tidak dapat dimutasi.',
                ]);
            }

            $oldPayment = [
                'status' => $lockedPayment->status->value,
                'voided_by' => $lockedPayment->voided_by,
                'voided_at' => $lockedPayment->voided_at?->toIso8601String(),
                'void_reason' => $lockedPayment->void_reason,
            ];

            $oldBill = [
                'paid_amount' => $bill->paid_amount,
                'status' => $bill->status->value,
            ];

            $lockedPayment->forceFill([
                'status' => SppPaymentStatus::Void,
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            $bill = $this->billState->recalculate($bill);

            $this->auditLogger->log(
                actor: $actor,
                module: 'spp',
                action: 'PAYMENT_VOID',
                entity: $lockedPayment,
                oldValues: $oldPayment,
                newValues: [
                    'status' => SppPaymentStatus::Void->value,
                    'voided_by' => $actor->id,
                    'voided_at' => $lockedPayment->voided_at?->toIso8601String(),
                    'void_reason' => $reason,
                ],
                metadata: [
                    'bill_id' => $bill->id,
                    'bill_before' => $oldBill,
                    'bill_after' => [
                        'paid_amount' => $bill->paid_amount,
                        'status' => $bill->status->value,
                    ],
                ]
            );

            return $lockedPayment->fresh(['bill', 'creator', 'voidedBy']);
        }, 3);
    }
}

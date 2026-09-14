<?php

namespace App\Actions\Spp;

use App\Enums\Finance\PaymentMethod;
use App\Enums\Finance\SppBillStatus;
use App\Enums\Finance\SppPaymentStatus;
use App\Events\Spp\SppPaymentPosted;
use App\Models\Auth\User;
use App\Models\Finance\SppBill;
use App\Models\Finance\SppPayment;
use App\Services\System\AuditLogger;
use App\Support\BusinessNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordSppPaymentAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function execute(
        User $actor,
        SppBill $bill,
        string $amount,
        PaymentMethod $paymentMethod,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): SppPayment {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use (
            $actor,
            $bill,
            $amount,
            $paymentMethod,
            $referenceNumber,
            $notes
        ): SppPayment {
            $lockedBill = SppBill::query()
                ->lockForUpdate()
                ->findOrFail($bill->id);

            if ($lockedBill->status === SppBillStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'bill' => 'Tagihan SPP sudah dibatalkan.',
                ]);
            }

            $outstanding = bcsub(
                $lockedBill->amount,
                $lockedBill->paid_amount,
                2
            );

            if (bccomp($outstanding, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'bill' => 'Tagihan SPP sudah lunas.',
                ]);
            }

            if (bccomp($amount, $outstanding, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pembayaran melebihi sisa tagihan.',
                ]);
            }

            $payment = SppPayment::query()->create([
                'payment_number' => BusinessNumber::sppPayment(),
                'receipt_number' => BusinessNumber::sppReceipt(),
                'spp_bill_id' => $lockedBill->id,
                'created_by' => $actor->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'payment_date' => now(),
                'status' => SppPaymentStatus::Posted,
                'notes' => $notes,
            ]);

            $newPaidAmount = bcadd(
                $lockedBill->paid_amount,
                $amount,
                2
            );

            $newStatus = bccomp(
                $newPaidAmount,
                $lockedBill->amount,
                2
            ) >= 0
                ? SppBillStatus::Paid
                : SppBillStatus::Partial;

            $oldBill = $lockedBill->getAttributes();

            $lockedBill->update([
                'paid_amount' => $newPaidAmount,
                'status' => $newStatus,
            ]);

            $this->auditLogger->log(
                $actor,
                'spp',
                'PAYMENT_POSTED',
                $payment,
                null,
                $payment->getAttributes(),
                [
                    'bill_id' => $lockedBill->id,
                    'bill_before' => $oldBill,
                    'bill_after' => $lockedBill->getAttributes(),
                ]
            );

            DB::afterCommit(
                fn () => event(
                    new SppPaymentPosted($payment->id)
                )
            );

            return $payment;
        });
    }

    private function assertPositiveAmount(string $amount): void
    {
        if (! is_numeric($amount) || bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal pembayaran harus lebih besar dari 0.',
            ]);
        }
    }
}

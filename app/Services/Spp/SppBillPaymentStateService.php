<?php

namespace App\Services\Spp;

use App\Enums\Finance\SppBillStatus;
use App\Enums\Finance\SppPaymentStatus;
use App\Models\Finance\SppBill;
use App\Models\Finance\SppPayment;

final class SppBillPaymentStateService
{
    public function recalculate(SppBill $bill): SppBill
    {
        $postedAmounts = SppPayment::query()
            ->where('spp_bill_id', $bill->id)
            ->where('status', SppPaymentStatus::Posted->value)
            ->pluck('amount');

        $paidAmount = $postedAmounts->reduce(
            fn (string $carry, mixed $amount): string => bcadd(
                $carry,
                (string) $amount,
                2
            ),
            '0.00'
        );

        if (bccomp($paidAmount, $bill->amount, 2) >= 0) {
            $status = SppBillStatus::Paid;
        } elseif (bccomp($paidAmount, '0', 2) > 0) {
            $status = SppBillStatus::Partial;
        } else {
            $status = $bill->due_date->lt(
                now(config('app.timezone'))->startOfDay()
            )
                ? SppBillStatus::Overdue
                : SppBillStatus::Pending;
        }

        $bill->forceFill([
            'paid_amount' => $paidAmount,
            'status' => $status,
        ])->save();

        return $bill->fresh();
    }
}

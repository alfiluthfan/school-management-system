<?php

namespace App\Services\Spp;

use App\Enums\Finance\PaymentMethod;
use App\Enums\Finance\SppBillStatus;
use App\Enums\Finance\SppPaymentStatus;
use App\Models\Finance\SppPayment;
use Illuminate\Validation\ValidationException;

final class SppPaymentCorrectionValidator
{
    /**
     * @param array<string, mixed> $changes
     * @return array{amount:string,payment_method:PaymentMethod,reference_number:?string,notes:?string}
     */
    public function resolve(SppPayment $payment, array $changes): array
    {
        $payment->loadMissing('bill');

        if ($payment->status !== SppPaymentStatus::Posted) {
            throw ValidationException::withMessages([
                'payment' => 'Hanya pembayaran POSTED yang dapat dikoreksi.',
            ]);
        }

        if ($payment->bill->status === SppBillStatus::Cancelled) {
            throw ValidationException::withMessages([
                'bill' => 'Tagihan SPP yang dibatalkan tidak dapat dimutasi.',
            ]);
        }

        $allowed = ['amount', 'payment_method', 'reference_number', 'notes'];

        if ($changes === []) {
            throw ValidationException::withMessages([
                'changes' => 'Minimal satu field koreksi harus diberikan.',
            ]);
        }

        foreach (array_keys($changes) as $key) {
            if (! in_array($key, $allowed, true)) {
                throw ValidationException::withMessages([
                    'changes.'.$key => 'Field ini tidak dapat dikoreksi.',
                ]);
            }
        }

        $amount = array_key_exists('amount', $changes)
            ? (string) $changes['amount']
            : (string) $payment->amount;

        if (! is_numeric($amount) || bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'changes.amount' => 'Nominal koreksi harus lebih besar dari 0.',
            ]);
        }

        $otherPaid = SppPayment::query()
            ->where('spp_bill_id', $payment->spp_bill_id)
            ->where('id', '!=', $payment->id)
            ->where('status', SppPaymentStatus::Posted->value)
            ->pluck('amount')
            ->reduce(
                fn (string $carry, mixed $value): string => bcadd(
                    $carry,
                    (string) $value,
                    2
                ),
                '0.00'
            );

        $maximumReplacement = bcsub(
            $payment->bill->amount,
            $otherPaid,
            2
        );

        if (bccomp($amount, $maximumReplacement, 2) > 0) {
            throw ValidationException::withMessages([
                'changes.amount' => 'Nominal koreksi menyebabkan total pembayaran melebihi tagihan.',
            ]);
        }

        $method = array_key_exists('payment_method', $changes)
            ? PaymentMethod::from((string) $changes['payment_method'])
            : $payment->payment_method;

        return [
            'amount' => $amount,
            'payment_method' => $method,
            'reference_number' => array_key_exists('reference_number', $changes)
                ? $changes['reference_number']
                : $payment->reference_number,
            'notes' => array_key_exists('notes', $changes)
                ? $changes['notes']
                : $payment->notes,
        ];
    }
}

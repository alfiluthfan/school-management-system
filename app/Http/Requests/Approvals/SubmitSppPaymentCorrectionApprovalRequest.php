<?php

namespace App\Http\Requests\Approvals;

use App\Enums\Finance\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubmitSppPaymentCorrectionApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'changes' => ['required', 'array:amount,payment_method,reference_number,notes'],
            'changes.amount' => ['sometimes', 'numeric', 'gt:0'],
            'changes.payment_method' => ['sometimes', Rule::enum(PaymentMethod::class)],
            'changes.reference_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'changes.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $changes = $this->input('changes', []);

            if (
                ! is_array($changes)
                || array_intersect(
                    array_keys($changes),
                    ['amount', 'payment_method', 'reference_number', 'notes']
                ) === []
            ) {
                $validator->errors()->add(
                    'changes',
                    'Minimal satu field koreksi harus diberikan.'
                );
            }
        });
    }
}

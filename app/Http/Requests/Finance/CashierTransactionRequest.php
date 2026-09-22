<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CashierTransactionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array
    {
        $savings = $this->routeIs('portal.finance.cashier.savings');
        return [
            'student_uuid' => ['required', 'uuid', Rule::exists('students', 'uuid')],
            'request_key' => ['required', 'uuid'],
            'operation' => [$savings ? 'required' : 'prohibited', Rule::in(['DEPOSIT', 'WITHDRAW'])],
            'bill_uuid' => [$savings ? 'prohibited' : 'required', 'uuid'],
            'amount' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/', 'numeric', 'gt:0'],
            'description' => [$savings && $this->input('operation') === 'WITHDRAW' ? 'required' : 'nullable', 'string', 'max:500'],
            'notes' => [$savings ? 'prohibited' : 'nullable', 'string', 'max:1000'],
        ];
    }
}

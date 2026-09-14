<?php

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawSavingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}

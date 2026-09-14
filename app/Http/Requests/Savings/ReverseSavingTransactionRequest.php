<?php

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;

class ReverseSavingTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}

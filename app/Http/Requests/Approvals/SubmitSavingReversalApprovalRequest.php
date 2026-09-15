<?php

namespace App\Http\Requests\Approvals;

use Illuminate\Foundation\Http\FormRequest;

class SubmitSavingReversalApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ];
    }
}

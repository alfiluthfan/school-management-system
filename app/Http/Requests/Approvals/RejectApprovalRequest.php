<?php

namespace App\Http\Requests\Approvals;

use Illuminate\Foundation\Http\FormRequest;

class RejectApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_notes' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ];
    }
}

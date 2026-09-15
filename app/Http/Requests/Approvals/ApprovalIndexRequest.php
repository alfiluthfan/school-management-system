<?php

namespace App\Http\Requests\Approvals;

use App\Enums\System\ApprovalAction;
use App\Enums\System\ApprovalModule;
use App\Enums\System\ApprovalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovalIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => [
                'sometimes',
                'integer',
                'between:1,100',
            ],
            'status' => [
                'sometimes',
                Rule::enum(ApprovalStatus::class),
            ],
            'module' => [
                'sometimes',
                Rule::enum(ApprovalModule::class),
            ],
            'action' => [
                'sometimes',
                Rule::enum(ApprovalAction::class),
            ],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 20);
    }
}

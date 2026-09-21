<?php

namespace App\Http\Requests\Approvals;

use App\Enums\System\ApprovalAction;
use App\Enums\System\ApprovalModule;
use App\Enums\System\ApprovalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class PortalApprovalIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Policy viewAny and ApprovalDataScope are checked in the controller/query.
        return true;
    }

    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::in(['all', 'mine'])],
            'status' => ['nullable', Rule::enum(ApprovalStatus::class)],
            'module' => ['nullable', Rule::enum(ApprovalModule::class)],
            'action' => ['nullable', Rule::enum(ApprovalAction::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('from');
            $to = $this->input('to');
            if ($from && $to && ! $validator->errors()->has('from')
                && ! $validator->errors()->has('to') && strcmp($from, $to) > 0) {
                $validator->errors()->add('to', 'Tanggal akhir harus sama atau sesudah tanggal awal.');
            }
        });
    }
}

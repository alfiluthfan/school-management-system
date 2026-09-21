<?php
namespace App\Http\Requests\Finance;

use App\Enums\Finance\SavingAccountStatus;
use App\Enums\Finance\SppBillStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class PortalFinanceIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(['savings', 'spp'])],
            'status' => ['nullable', Rule::in(array_merge(
                array_column(SavingAccountStatus::cases(), 'value'),
                array_column(SppBillStatus::cases(), 'value')
            ))],
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
                $validator->errors()->add('to', 'Tanggal akhir harus sama atau setelah tanggal awal.');
            }
        });
    }
}

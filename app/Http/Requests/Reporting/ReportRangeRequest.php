<?php

namespace App\Http\Requests\Reporting;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => [
                'required',
                'date_format:Y-m-d',
            ],
            'to' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:from',
            ],
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(function (
            Validator $validator
        ): void {
            $from = $this->input('from');
            $to = $this->input('to');

            if (! $from || ! $to) {
                return;
            }

            try {
                $days = CarbonImmutable::parse($from)
                    ->diffInDays(
                        CarbonImmutable::parse($to)
                    );
            } catch (\Throwable) {
                return;
            }

            if ($days > 366) {
                $validator->errors()->add(
                    'to',
                    'Rentang laporan maksimal 366 hari.'
                );
            }
        });
    }

    public function fromDate(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $this->validated('from'),
            config('app.timezone')
        )->startOfDay();
    }

    public function toDate(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $this->validated('to'),
            config('app.timezone')
        )->endOfDay();
    }
}

<?php

namespace App\Http\Requests\Reporting;

use App\Enums\Reporting\ReportExportStatus;
use App\Enums\Reporting\ReportType;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/** Validated read-only filter; it inherits the API's 366-day range guard. */
final class PortalReportIndexRequest extends ReportRangeRequest
{
    protected function prepareForValidation(): void
    {
        $today = CarbonImmutable::now(config('app.timezone'));
        $defaults = [];
        if (! $this->exists('from')) {
            $defaults['from'] = $today->startOfMonth()->toDateString();
        }
        if (! $this->exists('to')) {
            $defaults['to'] = $today->toDateString();
        }
        $this->merge($defaults);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'report_type' => ['sometimes', 'required', Rule::enum(ReportType::class)],
            'class_uuid' => ['sometimes', 'required', 'uuid'],
            'teacher_uuid' => ['sometimes', 'required', 'uuid'],
            'export_status' => ['sometimes', 'required', Rule::enum(ReportExportStatus::class)],
            'export_page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}

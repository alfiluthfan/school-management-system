<?php

namespace App\Http\Requests\Reporting;

use App\Enums\Reporting\ReportFormat;
use App\Enums\Reporting\ReportType;
use Illuminate\Validation\Rule;

class CreateReportExportRequest extends ReportRangeRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'report_type' => ['required', Rule::enum(ReportType::class)],
            'format' => ['required', Rule::enum(ReportFormat::class)],
            'class_uuid' => [
                'sometimes', 'required', 'uuid',
                Rule::prohibitedIf($this->input('report_type') !== ReportType::StudentAttendance->value),
            ],
            'teacher_uuid' => [
                'sometimes', 'required', 'uuid',
                Rule::prohibitedIf($this->input('report_type') !== ReportType::TeacherAttendance->value),
            ],
        ];
    }
}

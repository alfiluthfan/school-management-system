<?php

namespace App\Http\Requests\Reporting;

class StudentAttendanceReportRequest extends ReportRangeRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'class_uuid' => [
                'sometimes',
                'uuid',
            ],
        ];
    }
}

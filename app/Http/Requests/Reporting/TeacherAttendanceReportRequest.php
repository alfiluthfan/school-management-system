<?php

namespace App\Http\Requests\Reporting;

class TeacherAttendanceReportRequest extends ReportRangeRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'teacher_uuid' => [
                'sometimes',
                'uuid',
            ],
        ];
    }
}

<?php

namespace App\Http\Requests\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherAttendanceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'between:1,100',
            ],
            'status' => [
                'sometimes',
                Rule::enum(
                    AttendanceStatus::class
                ),
            ],
            'from' => [
                'sometimes',
                'date_format:Y-m-d',
            ],
            'to' => [
                'sometimes',
                'date_format:Y-m-d',
            ],
            'teacher_uuid' => [
                'sometimes',
                'uuid',
            ],
            'search' => [
                'sometimes',
                'string',
                'max:100',
            ],
        ];
    }

    public function perPage(): int
    {
        return $this->integer(
            'per_page',
            20
        );
    }
}

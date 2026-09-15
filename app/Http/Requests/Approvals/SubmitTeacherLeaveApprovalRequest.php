<?php

namespace App\Http\Requests\Approvals;

use App\Enums\Attendance\TeacherLeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitTeacherLeaveApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'leave_type' => ['required', Rule::enum(TeacherLeaveType::class)],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}

<?php

namespace App\Http\Requests\Approvals;

use App\Enums\Attendance\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubmitAttendanceCorrectionApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],

            'changes' => [
                'required',
                'array:status,check_in_at,check_out_at,notes',
            ],

            'changes.status' => [
                'sometimes',
                Rule::enum(AttendanceStatus::class),
            ],

            'changes.check_in_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'changes.check_out_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'changes.notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(function (
            Validator $validator
        ): void {
            $changes = $this->input(
                'changes',
                []
            );

            if (
                ! is_array($changes)
                || array_intersect(
                    array_keys($changes),
                    [
                        'status',
                        'check_in_at',
                        'check_out_at',
                        'notes',
                    ]
                ) === []
            ) {
                $validator->errors()->add(
                    'changes',
                    'Minimal satu field koreksi harus diberikan.'
                );
            }
        });
    }
}

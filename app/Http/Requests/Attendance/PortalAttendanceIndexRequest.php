<?php

namespace App\Http\Requests\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class PortalAttendanceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // viewAny policy and the scoped query are enforced by the web controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(['students', 'teachers'])],
            'status' => ['nullable', Rule::in(array_map(
                static fn (AttendanceStatus $status): string => $status->value,
                AttendanceStatus::cases()
            ))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
            'class_uuid' => ['nullable', 'uuid'],
            'teacher_uuid' => ['nullable', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
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

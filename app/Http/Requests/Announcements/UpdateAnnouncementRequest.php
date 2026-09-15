<?php

namespace App\Http\Requests\Announcements;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'string',
                'min:5',
                'max:160',
            ],
            'content' => [
                'sometimes',
                'string',
                'min:10',
                'max:10000',
            ],
            'target_roles' => [
                'sometimes',
                'array',
                'min:1',
            ],
            'target_roles.*' => [
                'required',
                'string',
                'distinct',
                'max:100',
            ],
            'expired_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(function (
            Validator $validator
        ): void {
            if (
                array_intersect(
                    array_keys($this->all()),
                    [
                        'title',
                        'content',
                        'target_roles',
                        'expired_at',
                    ]
                ) === []
            ) {
                $validator->errors()->add(
                    'announcement',
                    'Minimal satu field perubahan harus diberikan.'
                );
            }
        });
    }
}

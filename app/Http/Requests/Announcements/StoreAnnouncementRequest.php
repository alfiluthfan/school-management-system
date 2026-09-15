<?php

namespace App\Http\Requests\Announcements;

use App\Enums\Communication\AnnouncementTargetScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'min:5',
                'max:160',
            ],
            'content' => [
                'required',
                'string',
                'min:10',
                'max:10000',
            ],
            'target_scope' => [
                'required',
                Rule::enum(
                    AnnouncementTargetScope::class
                ),
            ],
            'class_uuid' => [
                'nullable',
                'uuid',
                'required_if:target_scope,CLASS',
                'prohibited_if:target_scope,SCHOOL',
            ],
            'target_roles' => [
                'required',
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
                'nullable',
                'date',
            ],
        ];
    }
}

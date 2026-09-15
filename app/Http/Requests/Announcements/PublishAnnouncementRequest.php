<?php

namespace App\Http\Requests\Announcements;

use Illuminate\Foundation\Http\FormRequest;

class PublishAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'publish_at' => [
                'nullable',
                'date',
            ],
            'expired_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ];
    }
}

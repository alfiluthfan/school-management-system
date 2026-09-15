<?php

namespace App\Http\Requests\Announcements;

use App\Enums\Communication\AnnouncementStatus;
use App\Enums\Communication\AnnouncementTargetScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementIndexRequest extends FormRequest
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
            'search' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'target_scope' => [
                'sometimes',
                Rule::enum(
                    AnnouncementTargetScope::class
                ),
            ],
            'status' => [
                'sometimes',
                Rule::enum(
                    AnnouncementStatus::class
                ),
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

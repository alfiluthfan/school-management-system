<?php
namespace App\Http\Requests\Announcements;

use App\Enums\Communication\AnnouncementStatus;
use App\Enums\Communication\AnnouncementTargetScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class PortalAnnouncementIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tab' => ['sometimes', Rule::in(['feed', 'mine'])],
            'search' => ['nullable', 'string', 'max:100'],
            'target_scope' => ['nullable', Rule::enum(AnnouncementTargetScope::class)],
            'status' => ['nullable', Rule::enum(AnnouncementStatus::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (($this->input('tab', 'feed') === 'feed') && $this->filled('status')) {
                $validator->errors()->add('status', 'Filter status hanya untuk pengumuman saya.');
            }
        });
    }
}

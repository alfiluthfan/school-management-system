<?php
namespace App\Http\Requests\Notifications;

use App\Enums\Communication\NotificationChannel;
use App\Enums\Communication\NotificationStatus;
use App\Enums\Communication\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationLogIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'page' => ['sometimes','integer','min:1'],
            'per_page' => ['sometimes','integer','between:1,100'],
            'status' => ['sometimes', Rule::enum(NotificationStatus::class)],
            'type' => ['sometimes', Rule::enum(NotificationType::class)],
            'channel' => ['sometimes', Rule::enum(NotificationChannel::class)],
            'search' => ['sometimes','string','max:100'],
            'from' => ['sometimes','date_format:Y-m-d'],
            'to' => ['sometimes','date_format:Y-m-d','after_or_equal:from'],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 20);
    }
}

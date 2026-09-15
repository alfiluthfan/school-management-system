<?php
namespace App\Http\Requests\Notifications;

use App\Enums\Communication\NotificationChannel;
use App\Enums\Communication\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationStatsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(NotificationType::class)],
            'channel' => ['sometimes', Rule::enum(NotificationChannel::class)],
            'from' => ['sometimes','date_format:Y-m-d'],
            'to' => ['sometimes','date_format:Y-m-d','after_or_equal:from'],
        ];
    }
}

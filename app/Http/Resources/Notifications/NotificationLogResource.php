<?php
namespace App\Http\Resources\Notifications;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'dedupe_key' => $this->dedupe_key,
            'type' => ['value'=>$this->type->value,'label'=>$this->type->label()],
            'channel' => ['value'=>$this->channel->value,'label'=>$this->channel->label()],
            'status' => ['value'=>$this->status->value,'label'=>$this->status->label()],
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'message' => $this->message,
            'provider_message_id' => $this->provider_message_id,
            'retry_count' => $this->retry_count,
            'error_message' => $this->error_message,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'recipient_user' => $this->whenLoaded('recipientUser', function (): ?array {
                if (!$this->recipientUser) return null;
                return [
                    'uuid' => $this->recipientUser->uuid,
                    'name' => $this->recipientUser->name,
                    'phone' => $this->recipientUser->phone,
                ];
            }),
            'student' => $this->whenLoaded('student', function (): ?array {
                if (!$this->student) return null;
                return [
                    'uuid' => $this->student->uuid,
                    'nis' => $this->student->nis,
                    'name' => $this->student->user?->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php
namespace App\Http\Resources\Notifications;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total' => (int) $this['total'],
            'queued' => (int) $this['queued'],
            'processing' => (int) $this['processing'],
            'sent' => (int) $this['sent'],
            'failed' => (int) $this['failed'],
            'cancelled' => (int) $this['cancelled'],
            'delivery_rate_percent' => (float) $this['delivery_rate_percent'],
        ];
    }
}

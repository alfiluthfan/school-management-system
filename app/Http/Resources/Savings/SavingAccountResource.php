<?php

namespace App\Http\Resources\Savings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'account_number' => $this->account_number,
            'current_balance' => $this->current_balance,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'student' => $this->whenLoaded('student', function (): array {
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

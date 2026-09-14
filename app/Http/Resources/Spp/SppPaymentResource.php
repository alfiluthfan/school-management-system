<?php

namespace App\Http\Resources\Spp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SppPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'payment_number' => $this->payment_number,
            'receipt_number' => $this->receipt_number,
            'amount' => $this->amount,
            'payment_method' => [
                'value' => $this->payment_method->value,
                'label' => $this->payment_method->label(),
            ],
            'reference_number' => $this->reference_number,
            'payment_date' => $this->payment_date?->toIso8601String(),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'notes' => $this->notes,
            'bill' => $this->whenLoaded('bill', function (): array {
                return [
                    'uuid' => $this->bill->uuid,
                    'bill_number' => $this->bill->bill_number,
                ];
            }),
            'created_by' => $this->whenLoaded('creator', function (): array {
                return [
                    'uuid' => $this->creator->uuid,
                    'name' => $this->creator->name,
                ];
            }),
            'void' => $this->when(
                $this->voided_at !== null,
                [
                    'voided_at' => $this->voided_at?->toIso8601String(),
                    'reason' => $this->void_reason,
                ]
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

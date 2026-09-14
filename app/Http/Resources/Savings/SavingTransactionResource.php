<?php

namespace App\Http\Resources\Savings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'transaction_number' => $this->transaction_number,
            'type' => [
                'value' => $this->transaction_type->value,
                'label' => $this->transaction_type->label(),
            ],
            'amount' => $this->amount,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'description' => $this->description,
            'transaction_date' => $this->transaction_date?->toIso8601String(),
            'reference_transaction' => $this->whenLoaded('referenceTransaction', function (): ?array {
                if (! $this->referenceTransaction) {
                    return null;
                }

                return [
                    'uuid' => $this->referenceTransaction->uuid,
                    'transaction_number' => $this->referenceTransaction->transaction_number,
                ];
            }),
            'created_by' => $this->whenLoaded('creator', function (): array {
                return [
                    'uuid' => $this->creator->uuid,
                    'name' => $this->creator->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

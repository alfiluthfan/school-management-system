<?php

namespace App\Http\Resources\Spp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SppBillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'bill_number' => $this->bill_number,
            'billing_period' => [
                'month' => $this->billing_month,
                'year' => $this->billing_year,
            ],
            'amount' => $this->amount,
            'paid_amount' => $this->paid_amount,
            'outstanding_amount' => bcsub($this->amount, $this->paid_amount, 2),
            'due_date' => $this->due_date?->toDateString(),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'notes' => $this->notes,
            'student' => $this->whenLoaded('student', function (): array {
                return [
                    'uuid' => $this->student->uuid,
                    'nis' => $this->student->nis,
                    'name' => $this->student->user?->name,
                ];
            }),
            'academic_year' => $this->whenLoaded('academicYear', function (): array {
                return [
                    'name' => $this->academicYear->name,
                    'start_date' => $this->academicYear->start_date?->toDateString(),
                    'end_date' => $this->academicYear->end_date?->toDateString(),
                ];
            }),
            'payments' => SppPaymentResource::collection(
                $this->whenLoaded('payments')
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

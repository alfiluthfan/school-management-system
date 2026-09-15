<?php

namespace App\Http\Resources\Approvals;

use App\Models\Attendance\StudentAttendance;
use App\Models\Finance\SavingTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'module' => [
                'value' => $this->module->value,
                'label' => $this->module->label(),
            ],

            'action' => [
                'value' => $this->action->value,
                'label' => $this->action->label(),
            ],

            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],

            'reason' => $this->reason,
            'request_payload' =>
                $this->request_payload,
            'review_notes' =>
                $this->review_notes,
            'reviewed_at' =>
                $this->reviewed_at?->toIso8601String(),

            'requester' => $this->whenLoaded(
                'requester',
                fn (): ?array => $this->requester
                    ? [
                        'uuid' =>
                            $this->requester->uuid,
                        'name' =>
                            $this->requester->name,
                    ]
                    : null
            ),

            'reviewer' => $this->whenLoaded(
                'reviewer',
                fn (): ?array => $this->reviewer
                    ? [
                        'uuid' =>
                            $this->reviewer->uuid,
                        'name' =>
                            $this->reviewer->name,
                    ]
                    : null
            ),

            'entity' => $this->whenLoaded(
                'entity',
                fn (): ?array =>
                    $this->entitySummary()
            ),

            'created_at' =>
                $this->created_at?->toIso8601String(),
            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }

    private function entitySummary(): ?array
    {
        if (! $this->entity) {
            return null;
        }

        $base = [
            'type' => $this->entity_type,
            'uuid' => $this->entity->uuid ?? null,
        ];

        if (
            $this->entity
            instanceof SavingTransaction
        ) {
            return [
                ...$base,
                'transaction_number' =>
                    $this->entity->transaction_number,
                'transaction_type' => [
                    'value' =>
                        $this->entity
                            ->transaction_type->value,
                    'label' =>
                        $this->entity
                            ->transaction_type->label(),
                ],
                'amount' =>
                    $this->entity->amount,
                'status' => [
                    'value' =>
                        $this->entity->status->value,
                    'label' =>
                        $this->entity->status->label(),
                ],
                'transaction_date' =>
                    $this->entity->transaction_date
                        ?->toIso8601String(),
            ];
        }

        if (
            $this->entity
            instanceof StudentAttendance
        ) {
            $this->entity->loadMissing(
                'student.user'
            );

            return [
                ...$base,
                'student' => [
                    'uuid' =>
                        $this->entity->student?->uuid,
                    'nis' =>
                        $this->entity->student?->nis,
                    'name' =>
                        $this->entity
                            ->student?->user?->name,
                ],
                'attendance_date' =>
                    $this->entity
                        ->attendance_date
                        ?->toDateString(),
                'status' => [
                    'value' =>
                        $this->entity->status->value,
                    'label' =>
                        $this->entity->status->label(),
                ],
                'check_in_at' =>
                    $this->entity
                        ->check_in_at
                        ?->toIso8601String(),
                'check_out_at' =>
                    $this->entity
                        ->check_out_at
                        ?->toIso8601String(),
                'late_minutes' =>
                    $this->entity->late_minutes,
            ];
        }

        return $base;
    }
}

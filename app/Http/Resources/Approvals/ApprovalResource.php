<?php

namespace App\Http\Resources\Approvals;

use App\Models\Attendance\StudentAttendance;
use App\Models\Attendance\TeacherLeave;
use App\Models\Finance\SavingTransaction;
use App\Models\Finance\SppPayment;
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
            'request_payload' => $this->request_payload,
            'review_notes' => $this->review_notes,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'requester' => $this->whenLoaded(
                'requester',
                fn (): ?array => $this->requester
                    ? ['uuid' => $this->requester->uuid, 'name' => $this->requester->name]
                    : null
            ),
            'reviewer' => $this->whenLoaded(
                'reviewer',
                fn (): ?array => $this->reviewer
                    ? ['uuid' => $this->reviewer->uuid, 'name' => $this->reviewer->name]
                    : null
            ),
            'entity' => $this->whenLoaded(
                'entity',
                fn (): ?array => $this->entitySummary()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
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

        if ($this->entity instanceof SavingTransaction) {
            return [
                ...$base,
                'transaction_number' => $this->entity->transaction_number,
                'transaction_type' => [
                    'value' => $this->entity->transaction_type->value,
                    'label' => $this->entity->transaction_type->label(),
                ],
                'amount' => $this->entity->amount,
                'status' => [
                    'value' => $this->entity->status->value,
                    'label' => $this->entity->status->label(),
                ],
                'transaction_date' => $this->entity->transaction_date?->toIso8601String(),
            ];
        }

        if ($this->entity instanceof StudentAttendance) {
            $this->entity->loadMissing('student.user');

            return [
                ...$base,
                'student' => [
                    'uuid' => $this->entity->student?->uuid,
                    'nis' => $this->entity->student?->nis,
                    'name' => $this->entity->student?->user?->name,
                ],
                'attendance_date' => $this->entity->attendance_date?->toDateString(),
                'status' => [
                    'value' => $this->entity->status->value,
                    'label' => $this->entity->status->label(),
                ],
                'check_in_at' => $this->entity->check_in_at?->toIso8601String(),
                'check_out_at' => $this->entity->check_out_at?->toIso8601String(),
                'late_minutes' => $this->entity->late_minutes,
            ];
        }

        if ($this->entity instanceof TeacherLeave) {
            $this->entity->loadMissing('teacher.user');

            return [
                ...$base,
                'teacher' => [
                    'uuid' => $this->entity->teacher?->uuid,
                    'name' => $this->entity->teacher?->user?->name,
                    'nip' => $this->entity->teacher?->nip,
                ],
                'start_date' => $this->entity->start_date?->toDateString(),
                'end_date' => $this->entity->end_date?->toDateString(),
                'leave_type' => [
                    'value' => $this->entity->leave_type->value,
                    'label' => $this->entity->leave_type->label(),
                ],
                'status' => [
                    'value' => $this->entity->status->value,
                    'label' => $this->entity->status->label(),
                ],
                'reason' => $this->entity->reason,
            ];
        }

        if ($this->entity instanceof SppPayment) {
            $this->entity->loadMissing('bill.student.user');

            return [
                ...$base,
                'payment_number' => $this->entity->payment_number,
                'receipt_number' => $this->entity->receipt_number,
                'student' => [
                    'uuid' => $this->entity->bill?->student?->uuid,
                    'name' => $this->entity->bill?->student?->user?->name,
                ],
                'amount' => $this->entity->amount,
                'payment_method' => [
                    'value' => $this->entity->payment_method->value,
                    'label' => $this->entity->payment_method->label(),
                ],
                'reference_number' => $this->entity->reference_number,
                'status' => [
                    'value' => $this->entity->status->value,
                    'label' => $this->entity->status->label(),
                ],
                'payment_date' => $this->entity->payment_date?->toIso8601String(),
            ];
        }

        return $base;
    }
}

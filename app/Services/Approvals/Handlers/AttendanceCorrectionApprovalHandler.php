<?php

namespace App\Services\Approvals\Handlers;

use App\Actions\Attendance\CorrectStudentAttendanceAction;
use App\Contracts\Approvals\ApprovalHandler;
use App\Models\Attendance\StudentAttendance;
use App\Models\Auth\User;
use App\Models\System\Approval;
use App\Services\Attendance\AttendanceCorrectionValidator;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class AttendanceCorrectionApprovalHandler implements ApprovalHandler
{
    public function __construct(
        private readonly CorrectStudentAttendanceAction $correctionAction,
        private readonly AttendanceCorrectionValidator $validator
    ) {
    }

    public function validateSubmission(
        User $requester,
        Model $entity,
        array $payload
    ): void {
        if (! $entity instanceof StudentAttendance) {
            throw new InvalidArgumentException(
                'Attendance correction approval membutuhkan StudentAttendance.'
            );
        }

        $changes = $payload['changes']
            ?? [];

        /*
         * Full domain validation happens before the PENDING approval is created.
         */
        $this->validator->resolve(
            $entity->fresh(['schedule']),
            is_array($changes)
                ? $changes
                : []
        );
    }

    public function pendingKey(
        Model $entity
    ): string {
        if (! $entity instanceof StudentAttendance) {
            throw new InvalidArgumentException(
                'Invalid attendance correction entity.'
            );
        }

        return sprintf(
            'ATTENDANCE:CORRECTION:%s:%s',
            $entity->getMorphClass(),
            $entity->uuid
        );
    }

    public function execute(
        User $reviewer,
        Approval $approval
    ): array {
        $attendance =
            StudentAttendance::query()
                ->findOrFail(
                    $approval->entity_id
                );

        if (
            $approval->entity_type
            !== $attendance->getMorphClass()
        ) {
            throw new InvalidArgumentException(
                'Approval entity tidak cocok dengan StudentAttendance.'
            );
        }

        $changes =
            $approval->request_payload['changes']
                ?? [];

        $corrected =
            $this->correctionAction->execute(
                actor: $reviewer,
                attendance: $attendance,
                changes: $changes,
                reason: $approval->reason
            );

        return [
            'executed_entity_type' =>
                $corrected->getMorphClass(),
            'executed_entity_id' =>
                $corrected->id,
            'executed_entity_uuid' =>
                $corrected->uuid,
            'corrected_status' =>
                $corrected->status->value,
            'late_minutes' =>
                $corrected->late_minutes,
        ];
    }
}

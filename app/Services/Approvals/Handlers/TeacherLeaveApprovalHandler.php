<?php

namespace App\Services\Approvals\Handlers;

use App\Contracts\Approvals\ApprovalHandler;
use App\Contracts\Approvals\ApprovalRejectionHandler;
use App\Enums\Attendance\TeacherLeaveStatus;
use App\Models\Attendance\TeacherLeave;
use App\Models\Auth\User;
use App\Models\System\Approval;
use App\Services\System\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class TeacherLeaveApprovalHandler implements ApprovalHandler, ApprovalRejectionHandler
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function validateSubmission(
        User $requester,
        Model $entity,
        array $payload
    ): void {
        if (! $entity instanceof TeacherLeave) {
            throw new InvalidArgumentException(
                'Teacher leave approval membutuhkan TeacherLeave.'
            );
        }

        if (
            ! $requester->teacher
            || $requester->teacher->id !== $entity->teacher_id
        ) {
            throw ValidationException::withMessages([
                'teacher' => 'Pengajuan izin hanya dapat dibuat oleh guru pemilik data.',
            ]);
        }

        if ($entity->status !== TeacherLeaveStatus::Pending) {
            throw ValidationException::withMessages([
                'leave' => 'Hanya izin berstatus PENDING yang dapat diajukan.',
            ]);
        }
    }

    public function pendingKey(Model $entity): string
    {
        if (! $entity instanceof TeacherLeave) {
            throw new InvalidArgumentException('Invalid teacher leave entity.');
        }

        return sprintf(
            'TEACHER_LEAVE:LEAVE_REQUEST:%s:%s',
            $entity->getMorphClass(),
            $entity->uuid
        );
    }

    public function execute(User $reviewer, Approval $approval): array
    {
        return DB::transaction(function () use ($reviewer, $approval): array {
            $leave = TeacherLeave::query()
                ->lockForUpdate()
                ->findOrFail($approval->entity_id);

            if ($approval->entity_type !== $leave->getMorphClass()) {
                throw new InvalidArgumentException(
                    'Approval entity tidak cocok dengan TeacherLeave.'
                );
            }

            if ($leave->status !== TeacherLeaveStatus::Pending) {
                throw ValidationException::withMessages([
                    'leave' => 'Izin guru sudah tidak berstatus PENDING.',
                ]);
            }

            $leave->forceFill([
                'status' => TeacherLeaveStatus::Approved,
            ])->save();

            $this->auditLogger->log(
                actor: $reviewer,
                module: 'teacher_leave',
                action: 'APPROVED',
                entity: $leave,
                oldValues: ['status' => TeacherLeaveStatus::Pending->value],
                newValues: ['status' => TeacherLeaveStatus::Approved->value],
                metadata: ['approval_id' => $approval->id]
            );

            return [
                'teacher_leave_uuid' => $leave->uuid,
                'teacher_leave_status' => TeacherLeaveStatus::Approved->value,
            ];
        }, 3);
    }

    public function reject(
        User $reviewer,
        Approval $approval,
        string $reviewNotes
    ): array {
        return DB::transaction(function () use (
            $reviewer,
            $approval,
            $reviewNotes
        ): array {
            $leave = TeacherLeave::query()
                ->lockForUpdate()
                ->findOrFail($approval->entity_id);

            if ($leave->status !== TeacherLeaveStatus::Pending) {
                throw ValidationException::withMessages([
                    'leave' => 'Izin guru sudah tidak berstatus PENDING.',
                ]);
            }

            $leave->forceFill([
                'status' => TeacherLeaveStatus::Rejected,
            ])->save();

            $this->auditLogger->log(
                actor: $reviewer,
                module: 'teacher_leave',
                action: 'REJECTED',
                entity: $leave,
                oldValues: ['status' => TeacherLeaveStatus::Pending->value],
                newValues: ['status' => TeacherLeaveStatus::Rejected->value],
                metadata: [
                    'approval_id' => $approval->id,
                    'review_notes' => $reviewNotes,
                ]
            );

            return [
                'teacher_leave_uuid' => $leave->uuid,
                'teacher_leave_status' => TeacherLeaveStatus::Rejected->value,
            ];
        }, 3);
    }
}

<?php

namespace App\Actions\Approvals;

use App\Enums\System\ApprovalStatus;
use App\Models\Auth\User;
use App\Models\System\Approval;
use App\Services\System\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RejectApprovalAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function execute(
        User $reviewer,
        Approval $approval,
        string $reviewNotes
    ): Approval {
        $approvalId = $approval->id;

        DB::transaction(function () use (
            $reviewer,
            $approvalId,
            $reviewNotes
        ): void {
            $locked = Approval::query()
                ->lockForUpdate()
                ->findOrFail($approvalId);

            if (
                $locked->status
                !== ApprovalStatus::Pending
            ) {
                throw ValidationException::withMessages([
                    'approval' =>
                        'Hanya approval PENDING yang dapat ditolak.',
                ]);
            }

            if ($locked->requested_by === $reviewer->id) {
                throw ValidationException::withMessages([
                    'approval' =>
                        'Requester tidak boleh mereview approval miliknya sendiri.',
                ]);
            }

            $locked->forceFill([
                'pending_key' => null,
                'status' => ApprovalStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'review_notes' => $reviewNotes,
                'reviewed_at' => now(),
            ])->save();

            $this->auditLogger->log(
                actor: $reviewer,
                module: 'approval',
                action: 'REJECT',
                entity: $locked,
                oldValues: [
                    'status' =>
                        ApprovalStatus::Pending->value,
                ],
                newValues: [
                    'status' =>
                        ApprovalStatus::Rejected->value,
                    'reviewed_by' => $reviewer->id,
                ]
            );
        }, 3);

        return Approval::query()
            ->with([
                'requester',
                'reviewer',
                'entity',
            ])
            ->findOrFail($approvalId);
    }
}

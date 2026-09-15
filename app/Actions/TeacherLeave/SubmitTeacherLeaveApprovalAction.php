<?php

namespace App\Actions\TeacherLeave;

use App\Actions\Approvals\SubmitApprovalRequestAction;
use App\Enums\Attendance\TeacherLeaveStatus;
use App\Enums\Attendance\TeacherLeaveType;
use App\Enums\System\ApprovalAction;
use App\Enums\System\ApprovalModule;
use App\Models\Academic\Teacher;
use App\Models\Attendance\TeacherLeave;
use App\Models\Auth\User;
use App\Models\System\Approval;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitTeacherLeaveApprovalAction
{
    public function __construct(
        private readonly SubmitApprovalRequestAction $submitApproval
    ) {
    }

    public function execute(
        User $requester,
        string $startDate,
        string $endDate,
        TeacherLeaveType $leaveType,
        string $reason
    ): Approval {
        $teacher = $requester->teacher;

        if (! $teacher) {
            throw ValidationException::withMessages([
                'teacher' => 'Akun ini tidak terhubung dengan data guru.',
            ]);
        }

        return DB::transaction(function () use (
            $requester,
            $teacher,
            $startDate,
            $endDate,
            $leaveType,
            $reason
        ): Approval {
            // Serialize leave submissions for the same teacher.
            Teacher::query()
                ->lockForUpdate()
                ->findOrFail($teacher->id);

            $overlapExists = TeacherLeave::query()
                ->where('teacher_id', $teacher->id)
                ->whereIn('status', [
                    TeacherLeaveStatus::Pending->value,
                    TeacherLeaveStatus::Approved->value,
                ])
                ->whereDate('start_date', '<=', $endDate)
                ->whereDate('end_date', '>=', $startDate)
                ->exists();

            if ($overlapExists) {
                throw ValidationException::withMessages([
                    'start_date' => 'Rentang izin bertabrakan dengan pengajuan lain yang masih aktif.',
                ]);
            }

            $leave = TeacherLeave::query()->create([
                'teacher_id' => $teacher->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'leave_type' => $leaveType,
                'reason' => $reason,
                'attachment_path' => null,
                'status' => TeacherLeaveStatus::Pending,
            ]);

            return $this->submitApproval->execute(
                requester: $requester,
                module: ApprovalModule::TeacherLeave,
                action: ApprovalAction::LeaveRequest,
                entity: $leave,
                reason: $reason,
                payload: [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'leave_type' => $leaveType->value,
                ]
            );
        }, 3);
    }
}

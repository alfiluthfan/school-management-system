<?php

namespace App\Services\Approvals;

use App\Contracts\Approvals\ApprovalHandler;
use App\Enums\System\ApprovalAction;
use App\Enums\System\ApprovalModule;
use App\Services\Approvals\Handlers\AttendanceCorrectionApprovalHandler;
use App\Services\Approvals\Handlers\SavingsReversalApprovalHandler;
use App\Services\Approvals\Handlers\SppPaymentCorrectionApprovalHandler;
use App\Services\Approvals\Handlers\SppPaymentVoidApprovalHandler;
use App\Services\Approvals\Handlers\TeacherLeaveApprovalHandler;
use DomainException;

final class ApprovalHandlerRegistry
{
    public function __construct(
        private readonly SavingsReversalApprovalHandler $savingsReversal,
        private readonly AttendanceCorrectionApprovalHandler $attendanceCorrection,
        private readonly TeacherLeaveApprovalHandler $teacherLeave,
        private readonly SppPaymentVoidApprovalHandler $sppPaymentVoid,
        private readonly SppPaymentCorrectionApprovalHandler $sppPaymentCorrection
    ) {
    }

    public function handler(
        ApprovalModule $module,
        ApprovalAction $action
    ): ApprovalHandler {
        return match ([$module, $action]) {
            [ApprovalModule::Saving, ApprovalAction::Void]
                => $this->savingsReversal,

            [ApprovalModule::Attendance, ApprovalAction::Correction]
                => $this->attendanceCorrection,

            [ApprovalModule::TeacherLeave, ApprovalAction::LeaveRequest]
                => $this->teacherLeave,

            [ApprovalModule::Spp, ApprovalAction::Void]
                => $this->sppPaymentVoid,

            [ApprovalModule::Spp, ApprovalAction::Correction]
                => $this->sppPaymentCorrection,

            default => throw new DomainException(
                sprintf(
                    'Approval handler belum tersedia untuk %s / %s.',
                    $module->value,
                    $action->value
                )
            ),
        };
    }
}

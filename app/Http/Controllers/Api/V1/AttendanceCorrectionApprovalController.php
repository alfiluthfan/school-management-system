<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Approvals\SubmitApprovalRequestAction;
use App\Enums\System\ApprovalAction;
use App\Enums\System\ApprovalModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\SubmitAttendanceCorrectionApprovalRequest;
use App\Http\Resources\Approvals\ApprovalResource;
use App\Models\Attendance\StudentAttendance;
use App\Models\System\Approval;
use Illuminate\Support\Facades\Gate;

class AttendanceCorrectionApprovalController extends Controller
{
    public function store(
        SubmitAttendanceCorrectionApprovalRequest $request,
        StudentAttendance $studentAttendance,
        SubmitApprovalRequestAction $action
    ) {
        Gate::authorize(
            'correct',
            $studentAttendance
        );

        Gate::authorize(
            'create',
            Approval::class
        );

        $approval = $action->execute(
            requester: $request->user(),
            module: ApprovalModule::Attendance,
            action: ApprovalAction::Correction,
            entity: $studentAttendance,
            reason: $request->validated('reason'),
            payload: [
                'changes' =>
                    $request->validated('changes'),
            ]
        );

        $approval->load([
            'requester',
            'reviewer',
            'entity',
        ]);

        return ApprovalResource::make($approval)
            ->response()
            ->setStatusCode(201);
    }
}

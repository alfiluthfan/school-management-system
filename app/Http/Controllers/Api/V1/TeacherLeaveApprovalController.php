<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TeacherLeave\SubmitTeacherLeaveApprovalAction;
use App\Enums\Attendance\TeacherLeaveType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\SubmitTeacherLeaveApprovalRequest;
use App\Http\Resources\Approvals\ApprovalResource;
use App\Models\Attendance\TeacherLeave;
use Illuminate\Support\Facades\Gate;

class TeacherLeaveApprovalController extends Controller
{
    public function store(
        SubmitTeacherLeaveApprovalRequest $request,
        SubmitTeacherLeaveApprovalAction $action
    ) {
        Gate::authorize('create', TeacherLeave::class);

        $approval = $action->execute(
            requester: $request->user(),
            startDate: $request->validated('start_date'),
            endDate: $request->validated('end_date'),
            leaveType: TeacherLeaveType::from($request->validated('leave_type')),
            reason: $request->validated('reason')
        );

        $approval->load(['requester', 'reviewer', 'entity']);

        return ApprovalResource::make($approval)
            ->response()
            ->setStatusCode(201);
    }
}

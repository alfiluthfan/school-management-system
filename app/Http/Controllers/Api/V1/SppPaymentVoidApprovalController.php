<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Approvals\SubmitApprovalRequestAction;
use App\Enums\System\ApprovalAction;
use App\Enums\System\ApprovalModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\SubmitSppPaymentVoidApprovalRequest;
use App\Http\Resources\Approvals\ApprovalResource;
use App\Models\Finance\SppPayment;
use App\Models\System\Approval;
use Illuminate\Support\Facades\Gate;

class SppPaymentVoidApprovalController extends Controller
{
    public function store(
        SubmitSppPaymentVoidApprovalRequest $request,
        SppPayment $sppPayment,
        SubmitApprovalRequestAction $action
    ) {
        Gate::authorize('void', $sppPayment);
        Gate::authorize('create', Approval::class);

        $approval = $action->execute(
            requester: $request->user(),
            module: ApprovalModule::Spp,
            action: ApprovalAction::Void,
            entity: $sppPayment,
            reason: $request->validated('reason'),
            payload: []
        );

        $approval->load(['requester', 'reviewer', 'entity']);

        return ApprovalResource::make($approval)
            ->response()
            ->setStatusCode(201);
    }
}

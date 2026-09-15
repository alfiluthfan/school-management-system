<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Approvals\SubmitApprovalRequestAction;
use App\Enums\System\ApprovalAction;
use App\Enums\System\ApprovalModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\SubmitSppPaymentCorrectionApprovalRequest;
use App\Http\Resources\Approvals\ApprovalResource;
use App\Models\Finance\SppPayment;
use App\Models\System\Approval;
use Illuminate\Support\Facades\Gate;

class SppPaymentCorrectionApprovalController extends Controller
{
    public function store(
        SubmitSppPaymentCorrectionApprovalRequest $request,
        SppPayment $sppPayment,
        SubmitApprovalRequestAction $action
    ) {
        Gate::authorize('correct', $sppPayment);
        Gate::authorize('create', Approval::class);

        $approval = $action->execute(
            requester: $request->user(),
            module: ApprovalModule::Spp,
            action: ApprovalAction::Correction,
            entity: $sppPayment,
            reason: $request->validated('reason'),
            payload: ['changes' => $request->validated('changes')]
        );

        $approval->load(['requester', 'reviewer', 'entity']);

        return ApprovalResource::make($approval)
            ->response()
            ->setStatusCode(201);
    }
}

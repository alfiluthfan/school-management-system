<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Approvals\SubmitApprovalRequestAction;
use App\Enums\System\ApprovalAction;
use App\Enums\System\ApprovalModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\SubmitSavingReversalApprovalRequest;
use App\Http\Resources\Approvals\ApprovalResource;
use App\Models\Finance\SavingTransaction;
use App\Models\System\Approval;
use Illuminate\Support\Facades\Gate;

class SavingReversalApprovalController extends Controller
{
    public function store(
        SubmitSavingReversalApprovalRequest $request,
        SavingTransaction $savingTransaction,
        SubmitApprovalRequestAction $action
    ) {
        Gate::authorize(
            'void',
            $savingTransaction
        );

        Gate::authorize(
            'create',
            Approval::class
        );

        $approval = $action->execute(
            requester: $request->user(),
            module: ApprovalModule::Saving,
            action: ApprovalAction::Void,
            entity: $savingTransaction,
            reason: $request->validated('reason'),
            payload: []
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

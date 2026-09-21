<?php

namespace App\Http\Controllers\Web;

use App\Actions\Approvals\ApproveApprovalAction;
use App\Actions\Approvals\RejectApprovalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\ApproveApprovalRequest;
use App\Http\Requests\Approvals\RejectApprovalRequest;
use App\Models\System\Approval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class PortalApprovalDecisionController extends Controller
{
    public function approve(ApproveApprovalRequest $request, Approval $approval, ApproveApprovalAction $action): RedirectResponse
    {
        Gate::authorize('view', $approval);
        Gate::authorize('approve', $approval);
        $action->execute(
            reviewer: $request->user(),
            approval: $approval,
            reviewNotes: $request->validated('review_notes')
        );
        return to_route('portal.approvals.show', $approval)
            ->with('success', 'Pengajuan berhasil disetujui.');
    }

    public function reject(RejectApprovalRequest $request, Approval $approval, RejectApprovalAction $action): RedirectResponse
    {
        Gate::authorize('view', $approval);
        Gate::authorize('reject', $approval);
        $action->execute(
            reviewer: $request->user(),
            approval: $approval,
            reviewNotes: $request->validated('review_notes')
        );
        return to_route('portal.approvals.show', $approval)
            ->with('success', 'Pengajuan berhasil ditolak.');
    }
}

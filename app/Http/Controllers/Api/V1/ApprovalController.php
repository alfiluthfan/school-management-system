<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Approvals\ApproveApprovalAction;
use App\Actions\Approvals\RejectApprovalAction;
use App\Authorization\ApprovalDataScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\ApprovalIndexRequest;
use App\Http\Requests\Approvals\ApproveApprovalRequest;
use App\Http\Requests\Approvals\RejectApprovalRequest;
use App\Http\Resources\Approvals\ApprovalResource;
use App\Models\System\Approval;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ApprovalController extends Controller
{
    public function index(
        ApprovalIndexRequest $request
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', Approval::class);

        $query = ApprovalDataScope::query(
            $request->user()
        );

        foreach (
            ['status', 'module', 'action']
            as $filter
        ) {
            if ($request->filled($filter)) {
                $query->where(
                    $filter,
                    $request->validated($filter)
                );
            }
        }

        $approvals = $query
            ->with([
                'requester',
                'reviewer',
                'entity',
            ])
            ->latest('created_at')
            ->paginate($request->perPage())
            ->withQueryString();

        return ApprovalResource::collection(
            $approvals
        );
    }

    public function show(
        Approval $approval
    ): ApprovalResource {
        Gate::authorize('view', $approval);

        $approval->load([
            'requester',
            'reviewer',
            'entity',
        ]);

        return ApprovalResource::make($approval);
    }

    public function approve(
        ApproveApprovalRequest $request,
        Approval $approval,
        ApproveApprovalAction $action
    ): ApprovalResource {
        Gate::authorize('approve', $approval);

        $approval = $action->execute(
            reviewer: $request->user(),
            approval: $approval,
            reviewNotes:
                $request->validated('review_notes')
        );

        return ApprovalResource::make($approval);
    }

    public function reject(
        RejectApprovalRequest $request,
        Approval $approval,
        RejectApprovalAction $action
    ): ApprovalResource {
        Gate::authorize('reject', $approval);

        $approval = $action->execute(
            reviewer: $request->user(),
            approval: $approval,
            reviewNotes:
                $request->validated('review_notes')
        );

        return ApprovalResource::make($approval);
    }
}

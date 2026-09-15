<?php

namespace App\Contracts\Approvals;

use App\Models\Auth\User;
use App\Models\System\Approval;
use Illuminate\Database\Eloquent\Model;

interface ApprovalHandler
{
    /**
     * Validate whether this entity may enter the approval workflow.
     *
     * @param array<string, mixed> $payload
     */
    public function validateSubmission(
        User $requester,
        Model $entity,
        array $payload
    ): void;

    /**
     * Unique while an equivalent request is PENDING.
     */
    public function pendingKey(Model $entity): string;

    /**
     * Execute the approved business operation.
     *
     * @return array<string, mixed> metadata for approval audit
     */
    public function execute(
        User $reviewer,
        Approval $approval
    ): array;
}

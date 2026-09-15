<?php

namespace App\Contracts\Approvals;

use App\Models\Auth\User;
use App\Models\System\Approval;

interface ApprovalRejectionHandler
{
    /**
     * @return array<string, mixed>
     */
    public function reject(
        User $reviewer,
        Approval $approval,
        string $reviewNotes
    ): array;
}

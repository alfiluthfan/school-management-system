<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\System\Approval;
use Illuminate\Auth\Access\Response;

class ApprovalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'approval.view.all',
            'approval.view.own',
        ]);
    }

    public function view(User $user, Approval $approval): Response
    {
        if ($user->hasPermission('approval.view.all')) {
            return Response::allow();
        }

        if (
            $user->hasPermission('approval.view.own')
            && $approval->requested_by === $user->id
        ) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('approval.submit');
    }

    public function approve(User $user, Approval $approval): bool
    {
        return $user->hasPermission('approval.approve');
    }

    public function reject(User $user, Approval $approval): bool
    {
        return $user->hasPermission('approval.reject');
    }
}

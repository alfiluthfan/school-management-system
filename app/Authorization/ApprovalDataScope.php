<?php

namespace App\Authorization;

use App\Models\Auth\User;
use App\Models\System\Approval;
use Illuminate\Database\Eloquent\Builder;

final class ApprovalDataScope
{
    public static function query(User $user): Builder
    {
        $query = Approval::query();

        if ($user->hasPermission('approval.view.all')) {
            return $query;
        }

        if ($user->hasPermission('approval.view.own')) {
            return $query->where(
                'requested_by',
                $user->id
            );
        }

        return $query->whereRaw('1 = 0');
    }
}

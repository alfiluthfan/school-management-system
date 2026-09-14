<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Finance\SppBill;
use Illuminate\Auth\Access\Response;

class SppBillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'spp.bill.view.all',
            'spp.bill.view.own',
            'spp.bill.view.child',
        ]);
    }

    public function view(User $user, SppBill $bill): Response
    {
        if ($user->hasPermission('spp.bill.view.all')) {
            return Response::allow();
        }

        if (
            $user->hasPermission('spp.bill.view.own')
            && $bill->student()->where('user_id', $user->id)->exists()
        ) {
            return Response::allow();
        }

        if (
            $user->hasPermission('spp.bill.view.child')
            && $user->guardian
            && $bill->student
                ->guardians()
                ->whereKey($user->guardian->id)
                ->exists()
        ) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('spp.bill.create');
    }

    public function update(User $user, SppBill $bill): bool
    {
        return $user->hasPermission('spp.bill.update');
    }

    public function cancel(User $user, SppBill $bill): bool
    {
        return $user->hasPermission('spp.bill.cancel');
    }
}

<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Finance\SavingAccount;
use Illuminate\Auth\Access\Response;

class SavingAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'saving.balance.view.all',
            'saving.balance.view.own',
            'saving.balance.view.child',
        ]);
    }

    public function view(User $user, SavingAccount $account): Response
    {
        if ($user->hasPermission('saving.balance.view.all')) {
            return Response::allow();
        }

        if (
            $user->hasPermission('saving.balance.view.own')
            && $account->student()->where('user_id', $user->id)->exists()
        ) {
            return Response::allow();
        }

        if (
            $user->hasPermission('saving.balance.view.child')
            && $user->guardian
            && $account->student
                ->guardians()
                ->whereKey($user->guardian->id)
                ->exists()
        ) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function deposit(User $user, SavingAccount $account): bool
    {
        return $user->hasPermission('saving.deposit.create');
    }

    public function withdraw(User $user, SavingAccount $account): bool
    {
        return $user->hasPermission('saving.withdrawal.create');
    }
}

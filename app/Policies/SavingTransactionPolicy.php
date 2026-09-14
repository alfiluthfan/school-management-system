<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Finance\SavingTransaction;
use Illuminate\Auth\Access\Response;

class SavingTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'saving.transaction.view.all',
            'saving.transaction.view.own',
            'saving.transaction.view.child',
        ]);
    }

    public function view(User $user, SavingTransaction $transaction): Response
    {
        if ($user->hasPermission('saving.transaction.view.all')) {
            return Response::allow();
        }

        $student = $transaction->savingAccount->student;

        if (
            $user->hasPermission('saving.transaction.view.own')
            && $student->user_id === $user->id
        ) {
            return Response::allow();
        }

        if (
            $user->hasPermission('saving.transaction.view.child')
            && $user->guardian
            && $student->guardians()
                ->whereKey($user->guardian->id)
                ->exists()
        ) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function update(User $user, SavingTransaction $transaction): bool
    {
        return $user->hasPermission('saving.transaction.update');
    }

    public function void(User $user, SavingTransaction $transaction): bool
    {
        return $user->hasPermission('saving.transaction.void');
    }
}

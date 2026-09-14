<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Finance\SppPayment;
use Illuminate\Auth\Access\Response;

class SppPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'spp.payment.view.all',
            'spp.payment.view.own',
            'spp.payment.view.child',
        ]);
    }

    public function view(User $user, SppPayment $payment): Response
    {
        if ($user->hasPermission('spp.payment.view.all')) {
            return Response::allow();
        }

        $student = $payment->bill->student;

        if (
            $user->hasPermission('spp.payment.view.own')
            && $student->user_id === $user->id
        ) {
            return Response::allow();
        }

        if (
            $user->hasPermission('spp.payment.view.child')
            && $user->guardian
            && $student->guardians()
                ->whereKey($user->guardian->id)
                ->exists()
        ) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('spp.payment.create');
    }

    public function correct(User $user, SppPayment $payment): bool
    {
        return $user->hasPermission('spp.payment.correct');
    }

    public function void(User $user, SppPayment $payment): bool
    {
        return $user->hasPermission('spp.payment.void');
    }
}

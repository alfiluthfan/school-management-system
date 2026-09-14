<?php

namespace App\Services\Notifications;

use App\Models\Academic\Guardian;
use App\Models\Academic\Student;
use App\Models\Auth\User;
use Illuminate\Support\Collection;

final class ParentRecipientResolver
{
    /**
     * Resolve all active parent users who:
     * - are linked to the student;
     * - opted in through receive_notification;
     * - have a phone number.
     *
     * Primary contacts are returned first.
     *
     * @return Collection<int, User>
     */
    public function forStudent(Student $student): Collection
    {
        return $student->guardians()
            ->wherePivot('receive_notification', true)
            ->with('user')
            ->get()
            ->sortByDesc(
                fn (Guardian $guardian): bool =>
                    (bool) $guardian->pivot->is_primary_contact
            )
            ->map(
                fn (Guardian $guardian): ?User =>
                    $guardian->user
            )
            ->filter(
                fn (?User $user): bool =>
                    $user !== null
                    && $user->is_active
                    && filled($user->phone)
            )
            ->unique('id')
            ->values();
    }
}

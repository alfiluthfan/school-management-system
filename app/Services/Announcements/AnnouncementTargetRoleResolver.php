<?php

namespace App\Services\Announcements;

use App\Models\Auth\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class AnnouncementTargetRoleResolver
{
    /**
     * @param array<int, string> $roleNames
     * @return Collection<int, Role>
     */
    public function resolve(
        array $roleNames
    ): Collection {
        $roleNames = collect($roleNames)
            ->map(
                fn (mixed $name): string =>
                    trim(
                        mb_strtolower(
                            (string) $name
                        )
                    )
            )
            ->filter()
            ->unique()
            ->values();

        if ($roleNames->isEmpty()) {
            throw ValidationException::withMessages([
                'target_roles' =>
                    'Minimal satu target role harus dipilih.',
            ]);
        }

        $roles = Role::query()
            ->whereIn(
                'name',
                $roleNames
            )
            ->get();

        if (
            $roles->count()
            !== $roleNames->count()
        ) {
            $unknown = $roleNames->diff(
                $roles->pluck('name')
            );

            throw ValidationException::withMessages([
                'target_roles' =>
                    'Role target tidak valid: '
                    .$unknown->implode(', '),
            ]);
        }

        return $roles;
    }
}

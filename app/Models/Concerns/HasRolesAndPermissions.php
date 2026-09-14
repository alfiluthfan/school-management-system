<?php

namespace App\Models\Concerns;

use Illuminate\Support\Collection;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 * @property \Illuminate\Database\Eloquent\Collection $roles
 */

trait HasRolesAndPermissions
{
    /**
     * Check whether the user has a role.
     */
    public function hasRole(string $role): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains('name', $role);
    }

    /**
     * Check whether the user has at least one of the supplied roles.
     *
     * @param array<int, string> $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        $this->loadMissing('roles');

        return $this->roles
            ->pluck('name')
            ->intersect($roles)
            ->isNotEmpty();
    }

    /**
     * Check whether the user has all supplied roles.
     *
     * @param array<int, string> $roles
     */
    public function hasAllRoles(array $roles): bool
    {
        $this->loadMissing('roles');

        $owned = $this->roles->pluck('name');

        return collect($roles)->every(
            fn(string $role): bool => $owned->contains($role)
        );
    }

    /**
     * Check permission inherited from any assigned role.
     */
    public function hasPermission(string $permission): bool
    {
        $this->loadMissing('roles.permissions');

        return $this->roles->contains(
            fn($role): bool => $role->permissions->contains('name', $permission)
        );
    }

    /**
     * Check whether the user has at least one supplied permission.
     *
     * @param array<int, string> $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        $permissionSet = $this->permissionNames();

        return $permissionSet->intersect($permissions)->isNotEmpty();
    }

    /**
     * Check whether the user has all supplied permissions.
     *
     * @param array<int, string> $permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        $permissionSet = $this->permissionNames();

        return collect($permissions)->every(
            fn(string $permission): bool => $permissionSet->contains($permission)
        );
    }

    /**
     * Flatten permissions inherited from all roles.
     *
     * @return Collection<int, string>
     */
    public function permissionNames(): Collection
    {
        $this->loadMissing('roles.permissions');

        return $this->roles
            ->flatMap(fn($role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isPrincipal(): bool
    {
        return $this->hasRole('principal');
    }

    public function isTeacher(): bool
    {
        return $this->hasRole('teacher');
    }

    public function isStudent(): bool
    {
        return $this->hasRole('student');
    }

    public function isParent(): bool
    {
        return $this->hasRole('parent');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use Database\Seeders\Support\RbacCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $permissions = Permission::query()
                ->pluck('id', 'name');

            foreach (RbacCatalog::rolePermissions() as $roleName => $permissionNames) {
                $role = Role::query()
                    ->where('name', $roleName)
                    ->first();

                if (! $role) {
                    throw new RuntimeException(
                        "Role [{$roleName}] belum tersedia. Jalankan RoleSeeder terlebih dahulu."
                    );
                }

                if ($permissionNames === '*') {
                    $role->permissions()->sync($permissions->values()->all());

                    continue;
                }

                $missing = collect($permissionNames)
                    ->reject(fn (string $name): bool => $permissions->has($name))
                    ->values();

                if ($missing->isNotEmpty()) {
                    throw new RuntimeException(
                        "Permission untuk role [{$roleName}] tidak ditemukan: "
                        . $missing->implode(', ')
                    );
                }

                $permissionIds = collect($permissionNames)
                    ->map(fn (string $name): int => (int) $permissions->get($name))
                    ->all();

                /*
                 * sync() intentionally makes the catalog above the source of truth
                 * for system role-permission mappings.
                 */
                $role->permissions()->sync($permissionIds);
            }
        });
    }
}

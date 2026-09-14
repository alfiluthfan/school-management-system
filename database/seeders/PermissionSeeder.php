<?php

namespace Database\Seeders;

use App\Models\Auth\Permission;
use Database\Seeders\Support\RbacCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (RbacCatalog::flattenedPermissions() as $permission) {
                Permission::query()->updateOrCreate(
                    ['name' => $permission['name']],
                    [
                        'display_name' => $permission['display_name'],
                        'module' => $permission['module'],
                        'description' => $permission['description'],
                    ],
                );
            }
        });
    }
}

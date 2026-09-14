<?php

namespace Database\Seeders;

use App\Models\Auth\Role;
use Database\Seeders\Support\RbacCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (RbacCatalog::roles() as $name => $attributes) {
                Role::query()->updateOrCreate(
                    ['name' => $name],
                    $attributes,
                );
            }
        });
    }
}

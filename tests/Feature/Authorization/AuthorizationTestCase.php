<?php

namespace Tests\Feature\Authorization;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchoolAuthorizationFixtures;
use Tests\TestCase;

abstract class AuthorizationTestCase extends TestCase
{
    use BuildsSchoolAuthorizationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }
}

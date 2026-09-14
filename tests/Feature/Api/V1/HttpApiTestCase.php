<?php

namespace Tests\Feature\Api\V1;

use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Concerns\BuildsHttpApiFixtures;
use Tests\TestCase;

abstract class HttpApiTestCase extends TestCase
{
    use BuildsHttpApiFixtures;
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Asia/Jakarta',
            'school.attendance.max_location_accuracy_meters' => 100,
        ]);

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }
}

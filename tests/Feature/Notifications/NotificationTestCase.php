<?php

namespace Tests\Feature\Notifications;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Concerns\BuildsHttpApiFixtures;
use Tests\TestCase;

abstract class NotificationTestCase extends TestCase
{
    use BuildsHttpApiFixtures;
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'school-notifications.whatsapp.default_country_calling_code'
                => '62',
            'school-notifications.queue'
                => 'notifications',
        ]);

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}

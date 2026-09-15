<?php

namespace Tests\Feature\Spp;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Concerns\BuildsHttpApiFixtures;
use Tests\TestCase;

class ProcessSppOverdueCommandTest extends TestCase
{
    use BuildsHttpApiFixtures;
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Asia/Jakarta',
            'school-notifications.spp_overdue'
            .'.first_reminder_after_days' => 1,
            'school-notifications.spp_overdue'
            .'.reminder_interval_days' => 3,
        ]);

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    public function test_command_accepts_explicit_date_and_prints_summary(): void
    {
        $this->artisan(
            'spp:process-overdue',
            [
                '--date' => '2026-09-14',
            ]
        )
            ->expectsOutput(
                'SPP overdue processing selesai.'
            )
            ->assertSuccessful();
    }

    public function test_command_rejects_invalid_date(): void
    {
        $this->artisan(
            'spp:process-overdue',
            [
                '--date' => '14-09-2026',
            ]
        )
            ->expectsOutput(
                'Option --date harus berformat YYYY-MM-DD.'
            )
            ->assertFailed();
    }
}

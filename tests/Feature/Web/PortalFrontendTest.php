<?php

namespace Tests\Feature\Web;

use App\Models\Auth\User;
use Tests\Feature\Api\V1\HttpApiTestCase;

final class PortalFrontendTest extends HttpApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The standard Laravel Vite middleware should not require a JS build in HTTP tests.
        $this->withoutVite();
    }

    public function test_guest_can_open_login_and_cannot_open_dashboard(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_valid_username_login_regenerates_session_and_opens_dashboard(): void
    {
        $admin = $this->createApiAdmin();

        $this->post('/login', [
            'identifier' => $admin->username,
            'password' => 'password',
            'remember' => false,
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($admin, 'web');
        $this->get('/dashboard')->assertOk();
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_valid_email_login_works(): void
    {
        $principal = $this->createApiPrincipal();

        $this->post('/login', [
            'identifier' => $principal->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($principal, 'web');
    }

    public function test_wrong_password_does_not_authenticate(): void
    {
        $admin = $this->createApiAdmin();

        $this->from('/login')->post('/login', [
            'identifier' => $admin->username,
            'password' => 'definitely-wrong',
        ])->assertRedirect('/login')->assertSessionHasErrors('identifier');

        $this->assertGuest('web');
    }

    public function test_inactive_account_is_denied_login(): void
    {
        $user = $this->createApiAdmin();
        $user->forceFill(['is_active' => false])->save();

        $this->from('/login')->post('/login', [
            'identifier' => $user->username,
            'password' => 'password',
        ])->assertRedirect('/login')->assertSessionHasErrors('identifier');

        $this->assertGuest('web');
    }

    public function test_existing_session_is_invalidated_after_account_deactivation(): void
    {
        $user = $this->createApiAdmin();
        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user, 'web')
            ->get('/dashboard')
            ->assertRedirect('/login');

        $this->assertGuest('web');
    }

    public function test_user_without_dashboard_permission_is_denied(): void
    {
        $user = User::query()->create([
            'name' => 'No access user',
            'username' => 'noaccess-'.$this->httpToken('front'),
            'email' => $this->httpToken('front').'@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'web')->get('/dashboard')->assertForbidden();
    }

    public function test_authenticated_student_can_open_scoped_dashboard_and_modules(): void
    {
        [$student] = $this->createApiStudent();

        $this->actingAs($student, 'web')
            ->get('/dashboard?date=2026-09-15')->assertOk();

        $this->get('/modules')->assertOk();
        $this->get('/dashboard?date=not-a-date')->assertSessionHasErrors('date');
    }

    public function test_logout_clears_login(): void
    {
        $admin = $this->createApiAdmin();

        $this->actingAs($admin, 'web')->post('/logout')->assertRedirect('/login');
        $this->assertGuest('web');
        $this->get('/dashboard')->assertRedirect('/login');
    }
}

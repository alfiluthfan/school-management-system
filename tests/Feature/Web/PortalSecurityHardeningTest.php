<?php

namespace Tests\Feature\Web;

use App\Models\Auth\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Api\V1\HttpApiTestCase;

final class PortalSecurityHardeningTest extends HttpApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_login_writes_server_revision_to_session(): void
    {
        $user = $this->createApiAdmin();
        $this->post('/login', [
            'identifier' => $user->username, 'password' => 'password',
        ])->assertRedirect('/dashboard')->assertSessionHas('portal.auth_version', 0);
        $this->get('/modules')->assertOk();
    }

    public function test_password_reset_revokes_legacy_and_remember_me_sessions_but_new_password_works(): void
    {
        $admin = $this->createApiAdmin();
        [$target] = $this->createApiStudent();
        $oldToken = $target->getRememberToken();
        $this->actingAs($admin, 'web')->post('/master-data/users/'.$target->uuid.'/reset-password', [
            'password' => 'SecureNewPassword_567!',
            'password_confirmation' => 'SecureNewPassword_567!',
        ])->assertRedirect()->assertSessionHas('success');

        $fresh = $target->fresh();
        $this->assertSame(1, (int) $fresh->portal_session_version);
        $this->assertNotSame($oldToken, $fresh->getRememberToken());
        $this->assertTrue(Hash::check('SecureNewPassword_567!', $fresh->password));
        $this->actingAs($fresh, 'web')->withSession(['portal.auth_version' => 0])
            ->get('/modules')->assertRedirect('/login');
        $this->assertGuest('web');
        $this->post('/login', ['identifier' => $fresh->username, 'password' => 'password'])
            ->assertSessionHasErrors('identifier');
        $this->post('/login', ['identifier' => $fresh->username,
            'password' => 'SecureNewPassword_567!'])
            ->assertRedirect('/dashboard')->assertSessionHas('portal.auth_version', 1);
        $this->get('/modules')->assertOk();
    }

    public function test_deactivation_then_reactivation_does_not_resurrect_old_session(): void
    {
        $admin = $this->createApiAdmin();
        [$target] = $this->createApiStudent();
        $this->actingAs($admin, 'web')->patch('/master-data/users/'.$target->uuid.'/state', [
            'active' => false,
        ])->assertRedirect()->assertSessionHas('success');
        $this->patch('/master-data/users/'.$target->uuid.'/state', ['active' => true])
            ->assertRedirect()->assertSessionHas('success');
        $fresh = $target->fresh();
        $this->assertTrue($fresh->is_active);
        $this->assertSame(1, (int) $fresh->portal_session_version);
        $this->actingAs($fresh, 'web')->withSession(['portal.auth_version' => 0])
            ->get('/modules')->assertRedirect('/login');
    }

    public function test_role_change_revokes_existing_session_and_identical_role_does_not(): void
    {
        $admin = $this->createApiAdmin();
        $target = $this->createApiUserWithRole('student');
        $this->actingAs($admin, 'web')->patch('/master-data/users/'.$target->uuid, [
            'roles' => ['student'],
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(0, (int) $target->fresh()->portal_session_version);
        $this->patch('/master-data/users/'.$target->uuid, ['roles' => ['student', 'parent']])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame(1, (int) $target->fresh()->portal_session_version);
        $this->actingAs($target->fresh(), 'web')->withSession(['portal.auth_version' => 0])
            ->get('/modules')->assertRedirect('/login');
    }

    public function test_old_unstamped_sessions_work_until_revoked_then_fail(): void
    {
        $admin = $this->createApiAdmin();
        $this->actingAs($admin, 'web')->get('/modules')->assertOk();
        $admin->forceFill(['portal_session_version' => 1])->save();
        $this->actingAs($admin->fresh(), 'web')->get('/modules')->assertRedirect('/login');
    }
}

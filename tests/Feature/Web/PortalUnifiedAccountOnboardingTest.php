<?php

namespace Tests\Feature\Web;

use App\Models\Academic\Guardian;
use App\Models\Academic\Student;
use App\Models\Academic\Teacher;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Api\V1\HttpApiTestCase;

final class PortalUnifiedAccountOnboardingTest extends HttpApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function account(string $role = 'student', array $overrides = []): array
    {
        $token = $this->httpToken('onboard');
        $data = [
            'role' => $role,
            'name' => 'Budi Siswa',
            'username' => $token,
            'email' => $token.'@example.test',
            'phone' => null,
            'password' => 'UniqueSecurePassword!789',
            'password_confirmation' => 'UniqueSecurePassword!789',
        ];
        if ($role === 'student') {
            $data['profile'] = [
                'nis' => 'NIS-'.$this->httpToken('nis'), 'nisn' => null,
                'gender' => 'MALE', 'birth_place' => 'Jakarta',
                'birth_date' => '2012-08-10', 'address' => 'Alamat',
                'admission_date' => '2026-07-01', 'graduation_date' => null,
                'status' => 'ACTIVE',
            ];
        } elseif ($role === 'teacher') {
            $data['profile'] = [
                'nip' => 'NIP-'.$this->httpToken('nip'),
                'employee_number' => 'EMP-'.$this->httpToken('emp'),
                'gender' => 'FEMALE', 'birth_place' => 'Bandung',
                'birth_date' => '1990-01-01', 'address' => 'Alamat',
                'employment_status' => 'PERMANENT', 'join_date' => '2026-07-01',
                'status' => 'ACTIVE',
            ];
        } elseif ($role === 'parent') {
            $data['profile'] = ['occupation' => 'Pedagang', 'address' => 'Alamat rumah'];
        }
        return [...$data, ...$overrides];
    }

    public function test_guest_and_principal_cannot_create_unified_accounts(): void
    {
        $this->post('/master-data/accounts', $this->account())->assertRedirect('/login');
        $principal = $this->createApiPrincipal();
        $this->actingAs($principal, 'web')->post('/master-data/accounts', $this->account())
            ->assertForbidden();
        $this->assertSame(1, User::query()->count());
    }

    public function test_admin_can_create_student_account_and_matching_profile_in_one_request(): void
    {
        $admin = $this->createApiAdmin();
        $payload = $this->account();
        $this->actingAs($admin, 'web')->post('/master-data/accounts', $payload)
            ->assertRedirect('/master-data?kind=students')->assertSessionHas('success');
        $user = User::query()->where('username', $payload['username'])->firstOrFail();
        $student = Student::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Budi Siswa', $student->user->name);
        $this->assertSame($payload['profile']['nis'], $student->nis);
        $this->assertSame(['student'], $user->roles()->pluck('name')->all());
        $this->assertTrue(Hash::check($payload['password'], $user->password));
        $this->assertDatabaseHas('audit_logs', ['module' => 'master-user', 'action' => 'CREATE', 'user_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'master-students', 'action' => 'CREATE', 'user_id' => $admin->id]);
        $this->assertStringNotContainsString($payload['password'], json_encode(
            \App\Models\System\AuditLog::query()->where('module', 'master-user')->latest('id')->firstOrFail()->new_values
        ));
    }

    public function test_teacher_profile_is_automatically_created_from_one_name(): void
    {
        $admin = $this->createApiAdmin();
        $payload = $this->account('teacher', ['name' => 'Bu Dina']);
        $this->actingAs($admin, 'web')->post('/master-data/accounts', $payload)
            ->assertRedirect('/master-data?kind=teachers')->assertSessionHas('success');
        $user = User::query()->where('username', $payload['username'])->firstOrFail();
        $teacher = Teacher::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Bu Dina', $teacher->user->name);
        $this->assertSame($payload['profile']['nip'], $teacher->nip);
        $this->assertSame(['teacher'], $user->roles()->pluck('name')->all());
    }

    public function test_parent_profile_is_created_in_same_transaction(): void
    {
        $admin = $this->createApiAdmin();
        $payload = $this->account('parent', ['name' => 'Orang Tua Budi']);
        $this->actingAs($admin, 'web')->post('/master-data/accounts', $payload)
            ->assertRedirect('/master-data?kind=parents')->assertSessionHas('success');
        $user = User::query()->where('username', $payload['username'])->firstOrFail();
        $guardian = Guardian::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Orang Tua Budi', $guardian->user->name);
        $this->assertSame(['parent'], $user->roles()->pluck('name')->all());
    }

    public function test_principal_creates_principal_account_without_adding_teacher_profile(): void
    {
        $admin = $this->createApiAdmin();
        $payload = $this->account('principal', ['name' => 'Kepala Sekolah']);
        $this->actingAs($admin, 'web')->post('/master-data/accounts', $payload)
            ->assertRedirect('/master-data?kind=users')->assertSessionHas('success');
        $user = User::query()->where('username', $payload['username'])->firstOrFail();
        $this->assertSame(['principal'], $user->roles()->pluck('name')->all());
        $this->assertFalse($user->teacher()->exists());
        $this->assertFalse($user->student()->exists());
    }

    public function test_multi_role_and_injected_profile_fields_are_rejected(): void
    {
        $admin = $this->createApiAdmin();
        $this->actingAs($admin, 'web');
        $this->post('/master-data/accounts', $this->account('student', ['role' => ['student','teacher']]))
            ->assertSessionHasErrors('role');
        $this->post('/master-data/accounts', $this->account('student', ['roles' => ['student','admin']]))
            ->assertSessionHasErrors('roles');
        $this->post('/master-data/accounts', $this->account('student', ['profile' => [
            ...$this->account('student')['profile'], 'user_id' => $admin->id,
        ]]))->assertSessionHasErrors('profile');
        $this->assertSame(1, User::query()->count());
    }

    public function test_missing_student_profile_and_unexpected_admin_profile_are_rejected(): void
    {
        $admin = $this->createApiAdmin();
        $this->actingAs($admin, 'web');
        $payload = $this->account();
        unset($payload['profile']);
        $this->post('/master-data/accounts', $payload)->assertSessionHasErrors('profile');
        $this->post('/master-data/accounts', $this->account('admin', [
            'profile' => ['nis' => 'INJECTION'],
        ]))->assertSessionHasErrors('profile');
        $this->assertSame(1, User::query()->count());
    }

    public function test_duplicate_nis_does_not_create_partial_account(): void
    {
        $admin = $this->createApiAdmin();
        [, $existingStudent] = $this->createApiStudent();
        $payload = $this->account();
        $payload['profile']['nis'] = $existingStudent->nis;
        $before = User::query()->count();
        $this->actingAs($admin, 'web')->post('/master-data/accounts', $payload)
            ->assertSessionHasErrors('profile.nis');
        $this->assertSame($before, User::query()->count());
        $this->assertDatabaseMissing('users', ['username' => $payload['username']]);
    }

    public function test_legacy_user_edit_and_create_reject_multiple_roles(): void
    {
        $admin = $this->createApiAdmin();
        $student = $this->createApiUserWithRole('student');
        $this->actingAs($admin, 'web');
        $this->post('/master-data/users', [
            'name' => 'Role Ganda', 'username' => $this->httpToken('legacy'),
            'email' => $this->httpToken('mail').'@example.test',
            'password' => 'SecurePassword_1234!', 'password_confirmation' => 'SecurePassword_1234!',
            'roles' => ['student','parent'],
        ])->assertSessionHasErrors('roles');
        $this->patch('/master-data/users/'.$student->uuid, ['roles' => ['student','parent']])
            ->assertSessionHasErrors('roles');
        $this->assertSame(['student'], $student->fresh()->roles()->pluck('name')->all());
    }

    public function test_read_only_users_receive_no_onboarding_roles_and_admin_sees_permitted_roles(): void
    {
        $principal = $this->createApiPrincipal();
        $this->actingAs($principal, 'web');
        $initial = $this->get('/master-data?kind=users')->assertOk();
        $this->assertSame([], $initial->viewData('page')['props']['master']['onboarding_roles']);
        $this->actingAs($this->createApiAdmin(), 'web');
        $response = $this->get('/master-data?kind=users')->assertOk();
        $roles = $response->viewData('page')['props']['master']['onboarding_roles'];
        $this->assertContains('student', array_column($roles, 'value'));
        $this->assertContains('principal', array_column($roles, 'value'));
    }
}

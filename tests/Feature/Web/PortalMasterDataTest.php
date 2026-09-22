<?php
namespace Tests\Feature\Web;

use App\Enums\Academic\EnrollmentStatus;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Guardian;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Student;
use App\Models\Academic\StudentClassEnrollment;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Api\V1\HttpApiTestCase;

final class PortalMasterDataTest extends HttpApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function inertiaGet(string $url)
    {
        $initial = $this->get($url)->assertOk()->assertViewHas('page');
        $version = data_get($initial->viewData('page'), 'version');
        return $this->get($url, [
            'X-Inertia' => 'true', 'X-Inertia-Version' => $version ?? '',
        ]);
    }

    private function account(array $changes = []): array
    {
        $token = $this->httpToken('master');
        return [
            'name' => 'Pengguna Baru', 'username' => $token,
            'email' => $token.'@example.test', 'phone' => null,
            'password' => 'LongUniquePassword_123!',
            'password_confirmation' => 'LongUniquePassword_123!',
            'roles' => ['student'], ...$changes,
        ];
    }

    private function studentFields(User $user, array $changes = []): array
    {
        return [
            'user_uuid' => $user->uuid, 'nis' => 'NIS-'.$this->httpToken('new'),
            'nisn' => null, 'gender' => 'MALE', 'birth_place' => 'Jakarta',
            'birth_date' => '2013-01-01', 'address' => 'Alamat pengujian',
            'admission_date' => '2026-07-01', 'graduation_date' => null,
            'status' => 'ACTIVE', ...$changes,
        ];
    }

    public function test_guest_redirects_and_unpermissioned_user_is_forbidden(): void
    {
        $this->get('/master-data')->assertRedirect('/login');
        $unpermissioned = User::query()->create([
            'name' => 'Tanpa Akses', 'username' => $this->httpToken('none'),
            'email' => $this->httpToken('none').'@example.test',
            'password' => 'password', 'is_active' => true,
        ]);
        $this->actingAs($unpermissioned, 'web')->get('/master-data')->assertForbidden();
    }

    public function test_principal_can_view_master_users_but_cannot_create_or_edit(): void
    {
        $principal = $this->createApiPrincipal();
        $admin = $this->createApiAdmin();
        $this->actingAs($principal, 'web');
        $this->inertiaGet('/master-data?kind=users')->assertOk()
            ->assertJsonPath('component', 'MasterData/Index')
            ->assertJsonPath('props.master.can.create', false)
            ->assertJsonPath('props.master.can.update', false)
            ->assertJsonPath('props.master.records.total', 2)
            ->assertJsonPath('props.master.records.data.0.fields', []);
        $this->post('/master-data/users', $this->account())->assertForbidden();
        $this->patch('/master-data/users/'.$admin->uuid, ['name' => 'Tidak boleh'])->assertForbidden();
    }

    public function test_teacher_sees_only_students_in_homeroom_and_assigned_classes(): void
    {
        [$teacherUser, $teacher] = $this->createApiTeacher();
        [, $otherTeacher] = $this->createApiTeacher();
        [, $mine] = $this->createApiStudent();
        [, $foreign] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $ownClass = $this->createApiClass($year, $teacher);
        $otherClass = $this->createApiClass($year, $otherTeacher);
        $this->enrollApiStudent($mine, $ownClass);
        $this->enrollApiStudent($foreign, $otherClass);
        $this->actingAs($teacherUser, 'web');
        $this->inertiaGet('/master-data?kind=students')
            ->assertJsonPath('props.master.records.total', 1)
            ->assertJsonPath('props.master.records.data.0.ref', $mine->uuid)
            ->assertJsonPath('props.master.records.data.0.fields', [])
            ->assertDontSee($foreign->uuid);
        $this->inertiaGet('/master-data?kind=classes')
            ->assertJsonPath('props.master.records.total', 1)
            ->assertJsonPath('props.master.records.data.0.ref', $ownClass->uuid);
        $this->get('/master-data?kind=users')->assertForbidden();
    }

    public function test_admin_creates_account_with_hashed_password_and_role_without_exposing_password(): void
    {
        $admin = $this->createApiAdmin();
        $payload = $this->account();
        $this->actingAs($admin, 'web')->post('/master-data/users', $payload)
            ->assertRedirect()->assertSessionHas('success');
        $created = User::query()->where('username', $payload['username'])->firstOrFail();
        $this->assertTrue($created->is_active);
        $this->assertTrue(Hash::check($payload['password'], $created->password));
        $this->assertTrue($created->hasRole('student'));
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'master-user', 'action' => 'CREATE', 'user_id' => $admin->id,
        ]);
        $this->assertStringNotContainsString($payload['password'],
            json_encode(\App\Models\System\AuditLog::query()->latest('id')->first()->new_values));
        $this->inertiaGet('/master-data?kind=users')->assertDontSee($payload['password'])
            ->assertDontSee($created->password);
    }

    public function test_user_payload_cannot_set_active_or_reset_password_from_generic_edit(): void
    {
        $admin = $this->createApiAdmin();
        $target = $this->createApiUserWithRole('student');
        $this->actingAs($admin, 'web');
        $this->post('/master-data/users', $this->account(['is_active' => false]))
            ->assertSessionHasErrors('is_active');
        $this->patch('/master-data/users/'.$target->uuid, ['password' => 'BypassPassword123!'])
            ->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password', $target->fresh()->password));
    }

    public function test_admin_cannot_change_own_role_or_deactivate_self(): void
    {
        $admin = $this->createApiAdmin();
        $this->actingAs($admin, 'web');
        $this->patch('/master-data/users/'.$admin->uuid, ['roles' => ['student']])
            ->assertSessionHasErrors('roles');
        $this->patch('/master-data/users/'.$admin->uuid.'/state', ['active' => false])
            ->assertSessionHasErrors('state');
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_admin_reset_password_requires_confirmation_and_audits_no_secret(): void
    {
        $admin = $this->createApiAdmin();
        $target = $this->createApiUserWithRole('student');
        $this->actingAs($admin, 'web');
        $this->post('/master-data/users/'.$target->uuid.'/reset-password', [
            'password' => 'NewSecurePassword_987!', 'password_confirmation' => 'not-matching',
        ])->assertSessionHasErrors('password');
        $this->post('/master-data/users/'.$target->uuid.'/reset-password', [
            'password' => 'NewSecurePassword_987!', 'password_confirmation' => 'NewSecurePassword_987!',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertTrue(Hash::check('NewSecurePassword_987!', $target->fresh()->password));
        $log = \App\Models\System\AuditLog::query()->where('action', 'RESET_PASSWORD')->firstOrFail();
        $this->assertStringNotContainsString('NewSecurePassword_987!', json_encode($log->new_values));
    }

    public function test_student_profile_requires_matching_unlinked_account_and_creates_profile(): void
    {
        $admin = $this->createApiAdmin();
        $wrong = $this->createApiUserWithRole('teacher');
        $student = $this->createApiUserWithRole('student');
        $this->actingAs($admin, 'web');
        $this->post('/master-data/students', $this->studentFields($wrong))
            ->assertSessionHasErrors('user_uuid');
        $data = $this->studentFields($student);
        $this->post('/master-data/students', $data)->assertRedirect()->assertSessionHas('success');
        $profile = Student::query()->where('user_id', $student->id)->firstOrFail();
        $this->assertSame($data['nis'], $profile->nis);
        $this->post('/master-data/students', $this->studentFields($student))
            ->assertSessionHasErrors('user_uuid');
    }

    public function test_class_and_year_creation_activation_and_enrollment_rejects_duplicate_year(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $oldYear = $this->createApiAcademicYear();
        $this->actingAs($admin, 'web');
        $this->post('/master-data/years', [
            'name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30',
        ])->assertRedirect()->assertSessionHas('success');
        $year = AcademicYear::query()->where('name', '2027/2028')->firstOrFail();
        $this->assertFalse($year->is_active);
        $this->post('/master-data/years/'.$year->id.'/activate')
            ->assertRedirect()->assertSessionHas('success');
        $this->assertTrue($year->fresh()->is_active);
        $this->assertFalse($oldYear->fresh()->is_active);
        $this->post('/master-data/classes', [
            'year_ref' => $year->id, 'teacher_uuid' => null, 'code' => 'VII-A-2027',
            'name' => 'VII A', 'grade_level' => '7', 'major' => null, 'status' => 'ACTIVE',
        ])->assertRedirect()->assertSessionHas('success');
        $class = SchoolClass::query()->where('code', 'VII-A-2027')->firstOrFail();
        $this->post('/master-data/students/'.$student->uuid.'/enrollments', [
            'class_uuid' => $class->uuid, 'joined_at' => '2027-07-12',
        ])->assertRedirect()->assertSessionHas('success');
        $this->post('/master-data/students/'.$student->uuid.'/enrollments', [
            'class_uuid' => $class->uuid, 'joined_at' => '2027-07-12',
        ])->assertSessionHasErrors('class_uuid');
        $this->assertSame(1, StudentClassEnrollment::query()->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active->value)->count());
        $this->patch('/master-data/years/'.$year->id, [
            'start_date' => '2027-01-01', 'end_date' => '2028-06-30',
        ])->assertSessionHasErrors('start_date');
    }

    public function test_parent_student_link_is_audited_and_duplicate_is_blocked(): void
    {
        $admin = $this->createApiAdmin();
        [, $guardian] = $this->createApiParent();
        [, $student] = $this->createApiStudent();
        $payload = [
            'student_uuid' => $student->uuid, 'relationship' => 'MOTHER',
            'is_primary_contact' => true, 'receive_notification' => true,
        ];
        $this->actingAs($admin, 'web')
            ->post('/master-data/parents/'.$guardian->uuid.'/students', $payload)
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('parent_students', [
            'parent_id' => $guardian->id, 'student_id' => $student->id,
            'relationship' => 'MOTHER', 'is_primary_contact' => true,
        ]);
        $this->post('/master-data/parents/'.$guardian->uuid.'/students', $payload)
            ->assertSessionHasErrors('student_uuid');
        $this->assertDatabaseCount('parent_students', 1);
    }

    public function test_principal_cannot_activate_year_or_link_students(): void
    {
        $principal = $this->createApiPrincipal();
        $year = $this->createApiAcademicYear();
        [, $guardian] = $this->createApiParent();
        [, $student] = $this->createApiStudent();
        $this->actingAs($principal, 'web');
        $this->post('/master-data/years/'.$year->id.'/activate')->assertForbidden();
        $this->post('/master-data/parents/'.$guardian->uuid.'/students', [
            'student_uuid' => $student->uuid, 'relationship' => 'FATHER',
            'is_primary_contact' => false, 'receive_notification' => true,
        ])->assertForbidden();
    }
}

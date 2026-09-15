<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Attendance\TeacherLeaveStatus;
use App\Enums\Attendance\TeacherLeaveType;
use App\Models\Attendance\TeacherLeave;
use App\Models\System\Approval;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Concerns\BuildsHttpApiFixtures;
use Tests\TestCase;

class TeacherLeaveApprovalHttpTest extends TestCase
{
    use BuildsHttpApiFixtures;
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_teacher_can_submit_leave_and_generic_approval_is_pending(): void
    {
        [$user, $teacher] = $this->createApiTeacher();

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.teacher-leaves.store'), [
                'start_date' => '2026-09-20',
                'end_date' => '2026-09-21',
                'leave_type' => TeacherLeaveType::Sick->value,
                'reason' => 'Kondisi kesehatan membutuhkan istirahat.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.module.value', 'TEACHER_LEAVE')
            ->assertJsonPath('data.action.value', 'LEAVE_REQUEST')
            ->assertJsonPath('data.status.value', 'PENDING');

        $leave = TeacherLeave::query()->where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertSame(TeacherLeaveStatus::Pending, $leave->status);
        $this->assertDatabaseHas('approvals', [
            'uuid' => $response->json('data.uuid'),
            'entity_id' => $leave->id,
            'status' => 'PENDING',
        ]);
    }

    public function test_overlapping_pending_leave_is_rejected(): void
    {
        [$user] = $this->createApiTeacher();

        $this->actingAs($user)->postJson(route('api.v1.teacher-leaves.store'), [
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-22',
            'leave_type' => TeacherLeaveType::Permission->value,
            'reason' => 'Keperluan keluarga yang tidak dapat ditinggalkan.',
        ])->assertCreated();

        $this->actingAs($user)->postJson(route('api.v1.teacher-leaves.store'), [
            'start_date' => '2026-09-21',
            'end_date' => '2026-09-23',
            'leave_type' => TeacherLeaveType::Permission->value,
            'reason' => 'Pengajuan yang bertabrakan dengan izin sebelumnya.',
        ])->assertUnprocessable()->assertJsonValidationErrors('start_date');
    }

    public function test_principal_approval_updates_leave_status(): void
    {
        [$teacherUser] = $this->createApiTeacher();
        $principal = $this->createApiPrincipal();
        $approval = $this->submitLeave($teacherUser);

        $this->actingAs($principal)
            ->postJson(route('api.v1.approvals.approve', $approval), [
                'review_notes' => 'Pengajuan sudah diverifikasi.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'APPROVED');

        $leave = TeacherLeave::query()->findOrFail($approval->entity_id);
        $this->assertSame(TeacherLeaveStatus::Approved, $leave->status);
    }

    public function test_principal_rejection_updates_leave_and_allows_new_request(): void
    {
        [$teacherUser] = $this->createApiTeacher();
        $principal = $this->createApiPrincipal();
        $approval = $this->submitLeave($teacherUser);

        $this->actingAs($principal)
            ->postJson(route('api.v1.approvals.reject', $approval), [
                'review_notes' => 'Jadwal perlu disesuaikan terlebih dahulu.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'REJECTED');

        $leave = TeacherLeave::query()->findOrFail($approval->entity_id);
        $this->assertSame(TeacherLeaveStatus::Rejected, $leave->status);

        $this->actingAs($teacherUser)->postJson(route('api.v1.teacher-leaves.store'), [
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-21',
            'leave_type' => TeacherLeaveType::Permission->value,
            'reason' => 'Pengajuan ulang setelah penyesuaian jadwal.',
        ])->assertCreated();
    }

    public function test_admin_without_teacher_profile_cannot_submit_teacher_leave(): void
    {
        $admin = $this->createApiAdmin();

        $this->actingAs($admin)->postJson(route('api.v1.teacher-leaves.store'), [
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-21',
            'leave_type' => TeacherLeaveType::Sick->value,
            'reason' => 'Admin tidak memiliki profil guru.',
        ])->assertForbidden();
    }

    private function submitLeave($teacherUser): Approval
    {
        $response = $this->actingAs($teacherUser)
            ->postJson(route('api.v1.teacher-leaves.store'), [
                'start_date' => '2026-09-20',
                'end_date' => '2026-09-21',
                'leave_type' => TeacherLeaveType::Sick->value,
                'reason' => 'Kondisi kesehatan membutuhkan istirahat.',
            ])->assertCreated();

        return Approval::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();
    }
}

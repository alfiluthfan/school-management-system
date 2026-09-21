<?php

namespace Tests\Feature\Web;

use App\Enums\Attendance\TeacherLeaveType;
use App\Enums\System\ApprovalStatus;
use App\Models\Auth\User;
use App\Models\System\Approval;
use Carbon\CarbonImmutable;
use Tests\Feature\Api\V1\HttpApiTestCase;

final class PortalApprovalTest extends HttpApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-09-21 08:00:00', 'Asia/Jakarta')
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function inertiaGet(string $url)
    {
        // Avoid false 409 Asset Version Conflict: first read the current version
        // from the initial HTML visit and send it with the subsequent Inertia GET.
        $initial = $this->get($url)->assertOk()->assertViewHas('page');
        $version = data_get($initial->viewData('page'), 'version');

        return $this->get($url, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version ?? '',
        ]);
    }

    private function submitLeave(User $teacher): Approval
    {
        $response = $this->actingAs($teacher, 'web')->postJson(
            route('api.v1.teacher-leaves.store'), [
                'start_date' => '2026-09-25',
                'end_date' => '2026-09-25',
                'leave_type' => TeacherLeaveType::Permission->value,
                'reason' => 'Keperluan keluarga yang harus ditangani.',
            ]
        )->assertCreated();

        return Approval::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();
    }

    public function test_guest_redirected_and_user_without_permission_forbidden(): void
    {
        $this->get('/approvals')->assertRedirect('/login');
        $user = User::query()->create([
            'name' => 'No approval access',
            'username' => $this->httpToken('no-approval'),
            'email' => $this->httpToken('no-approval').'@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->actingAs($user, 'web')->get('/approvals')->assertForbidden();
    }

    public function test_teacher_only_sees_own_approval_and_cannot_expand_scope(): void
    {
        [$teacherA] = $this->createApiTeacher();
        [$teacherB] = $this->createApiTeacher();
        $own = $this->submitLeave($teacherA);
        $other = $this->submitLeave($teacherB);

        $this->actingAs($teacherA, 'web');
        $this->inertiaGet('/approvals')->assertOk()
            ->assertJsonPath('component', 'Approvals/Index')
            ->assertJsonPath('props.approvals.records.total', 1)
            ->assertJsonPath('props.approvals.records.data.0.uuid', $own->uuid)
            ->assertJsonPath('props.approvals.records.data.0.can.approve', false)
            ->assertDontSee($other->uuid);

        $this->inertiaGet('/approvals?scope=all')->assertOk()
            ->assertJsonPath('props.approvals.scope', 'mine')
            ->assertJsonPath('props.approvals.records.total', 1)
            ->assertDontSee($other->uuid);
        $this->get('/approvals/'.$other->uuid)->assertNotFound();
    }

    public function test_principal_sees_pending_and_can_filter_mine_without_leaking_payload(): void
    {
        [$teacher] = $this->createApiTeacher();
        $principal = $this->createApiPrincipal();
        $approval = $this->submitLeave($teacher);
        $approval->forceFill(['request_payload' => [
            'changes' => ['notes' => 'Revisi jadwal', 'student_id' => 123456],
            'password' => 'hidden-secret',
        ]])->save();

        $this->actingAs($principal, 'web');
        $this->inertiaGet('/approvals?status=PENDING')->assertOk()
            ->assertJsonPath('props.approvals.records.total', 1)
            ->assertJsonPath('props.approvals.records.data.0.uuid', $approval->uuid)
            ->assertJsonPath('props.approvals.records.data.0.can.approve', true)
            ->assertJsonMissingPath('props.approvals.records.data.0.request_payload')
            ->assertJsonMissingPath('props.approvals.records.data.0.id')
            ->assertDontSee('hidden-secret');

        $this->inertiaGet('/approvals?scope=mine')->assertOk()
            ->assertJsonPath('props.approvals.records.total', 0);
    }

    public function test_detail_hides_unapproved_payload_keys_but_keeps_allowed_changes(): void
    {
        [$teacher] = $this->createApiTeacher();
        $principal = $this->createApiPrincipal();
        $approval = $this->submitLeave($teacher);
        $approval->forceFill(['request_payload' => [
            'changes' => [
                'notes' => 'Koreksi catatan',
                'student_id' => 123456,
                'password' => 'should-not-be-present',
            ],
        ]])->save();

        $this->actingAs($principal, 'web');
        $this->inertiaGet('/approvals/'.$approval->uuid)->assertOk()
            ->assertJsonPath('component', 'Approvals/Show')
            ->assertJsonPath('props.approval.uuid', $approval->uuid)
            ->assertJsonMissingPath('props.approval.id')
            ->assertJsonPath('props.approval.request_payload.changes.notes', 'Koreksi catatan')
            ->assertJsonMissingPath('props.approval.request_payload.changes.student_id')
            ->assertJsonMissingPath('props.approval.request_payload.changes.password')
            ->assertDontSee('should-not-be-present');
    }

    public function test_principal_approval_executes_existing_handler_once_and_shows_history(): void
    {
        [$teacher] = $this->createApiTeacher();
        $principal = $this->createApiPrincipal();
        $approval = $this->submitLeave($teacher);
        $this->actingAs($principal, 'web');

        $this->post('/approvals/'.$approval->uuid.'/approve', [
            'review_notes' => 'Dokumen izin telah diverifikasi.',
        ])->assertRedirect('/approvals/'.$approval->uuid)
            ->assertSessionHas('success');
        $this->assertDatabaseHas('approvals', [
            'uuid' => $approval->uuid,
            'status' => ApprovalStatus::Approved->value,
            'reviewed_by' => $principal->id,
        ]);
        $this->assertDatabaseHas('teacher_leaves', [
            'id' => $approval->entity_id,
            'status' => 'APPROVED',
        ]);
        $this->inertiaGet('/approvals/'.$approval->uuid)->assertOk()
            ->assertJsonPath('props.approval.status.value', 'APPROVED')
            ->assertJsonPath('props.approval.can.approve', false)
            ->assertJsonPath('props.approval.timeline.1.title', 'Pengajuan disetujui');

        $this->from('/approvals/'.$approval->uuid)
            ->post('/approvals/'.$approval->uuid.'/approve', [])
            ->assertRedirect('/approvals/'.$approval->uuid)
            ->assertSessionHasErrors('approval');
        $this->assertDatabaseHas('approvals', ['uuid' => $approval->uuid, 'status' => 'APPROVED']);
    }

    public function test_rejection_requires_notes_and_records_decision(): void
    {
        [$teacher] = $this->createApiTeacher();
        $principal = $this->createApiPrincipal();
        $approval = $this->submitLeave($teacher);
        $this->actingAs($principal, 'web')->from('/approvals/'.$approval->uuid)
            ->post('/approvals/'.$approval->uuid.'/reject', ['review_notes' => 'No'])
            ->assertSessionHasErrors('review_notes');
        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);

        $this->post('/approvals/'.$approval->uuid.'/reject', [
            'review_notes' => 'Jadwal perlu diperbaiki sebelum disetujui.',
        ])->assertRedirect('/approvals/'.$approval->uuid)->assertSessionHas('success');
        $this->assertDatabaseHas('approvals', [
            'uuid' => $approval->uuid,
            'status' => 'REJECTED',
            'reviewed_by' => $principal->id,
        ]);
        $this->assertDatabaseHas('teacher_leaves', [
            'id' => $approval->entity_id,
            'status' => 'REJECTED',
        ]);
        $this->inertiaGet('/approvals/'.$approval->uuid)->assertJsonPath('props.approval.timeline.1.title', 'Pengajuan ditolak');
    }

    public function test_requester_and_unrelated_teacher_cannot_review(): void
    {
        [$teacher] = $this->createApiTeacher();
        [$otherTeacher] = $this->createApiTeacher();
        $approval = $this->submitLeave($teacher);
        $this->actingAs($teacher, 'web')->post('/approvals/'.$approval->uuid.'/approve')
            ->assertForbidden();
        $this->actingAs($otherTeacher, 'web')->post('/approvals/'.$approval->uuid.'/reject', [
            'review_notes' => 'Tidak berwenang menolak.',
        ])->assertNotFound();
        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);
    }

    public function test_principal_cannot_review_own_request(): void
    {
        [$teacher] = $this->createApiTeacher();
        $principal = $this->createApiPrincipal();
        $approval = $this->submitLeave($teacher);
        $approval->forceFill(['requested_by' => $principal->id])->save();

        $this->actingAs($principal, 'web');
        $this->inertiaGet('/approvals/'.$approval->uuid)->assertJsonPath('props.approval.can.approve', false)
            ->assertJsonPath('props.approval.can.reject', false);
        $this->post('/approvals/'.$approval->uuid.'/approve')->assertForbidden();
        $this->post('/approvals/'.$approval->uuid.'/reject', [
            'review_notes' => 'Tidak diperbolehkan mereview sendiri.',
        ])->assertForbidden();
        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);
    }

    public function test_filters_are_validated_and_history_filter_is_scoped(): void
    {
        [$teacher] = $this->createApiTeacher();
        $principal = $this->createApiPrincipal();
        $approval = $this->submitLeave($teacher);
        $this->actingAs($principal, 'web');
        $this->get('/approvals?status=HACKED')->assertSessionHasErrors('status');
        $this->get('/approvals?from=2026-09-26&to=2026-09-25')->assertSessionHasErrors('to');
        $this->post('/approvals/'.$approval->uuid.'/reject', [
            'review_notes' => 'Pengajuan perlu dijadwalkan kembali.',
        ])->assertRedirect('/approvals/'.$approval->uuid);
        $this->inertiaGet('/approvals?status=REJECTED')->assertOk()
            ->assertJsonPath('props.approvals.records.total', 1)
            ->assertJsonPath('props.approvals.records.data.0.uuid', $approval->uuid);
        $this->inertiaGet('/approvals?status=PENDING')->assertOk()
            ->assertJsonPath('props.approvals.records.total', 0);
    }
}

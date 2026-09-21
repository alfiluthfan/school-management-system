<?php
namespace Tests\Feature\Web;

use App\Enums\Communication\AnnouncementStatus;
use App\Enums\Communication\AnnouncementTargetScope;
use App\Models\Academic\SchoolClass;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Communication\Announcement;
use Carbon\CarbonImmutable;
use Tests\Feature\Api\V1\HttpApiTestCase;

final class PortalAnnouncementTest extends HttpApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-21 10:00:00', 'Asia/Jakarta'));
    }

    private function inertiaGet(string $url)
    {
        // Follow real Inertia version handshake to avoid false 409 conflicts.
        $initial = $this->get($url)->assertOk()->assertViewHas('page');
        $version = data_get($initial->viewData('page'), 'version');
        return $this->get($url, [
            'X-Inertia' => 'true', 'X-Inertia-Version' => $version ?? '',
        ]);
    }

    private function published(User $creator, array $targetRoles,
        AnnouncementTargetScope $scope = AnnouncementTargetScope::School,
        ?SchoolClass $class = null, ?string $publishAt = null,
        ?string $expiredAt = null): Announcement
    {
        $announcement = Announcement::query()->create([
            'created_by' => $creator->id, 'class_id' => $class?->id,
            'title' => 'Agenda kegiatan sekolah dan kelas',
            'content' => 'Pengumuman kegiatan dengan informasi waktu dan tempat.',
            'target_scope' => $scope, 'status' => AnnouncementStatus::Published,
            'publish_at' => $publishAt ?? CarbonImmutable::now('Asia/Jakarta'),
            'expired_at' => $expiredAt,
        ]);
        $announcement->roles()->sync(Role::query()->whereIn('name', $targetRoles)->pluck('id'));
        return $announcement->fresh(['roles']);
    }

    private function draftPayload(array $changes = []): array
    {
        return [
            'title' => 'Agenda kegiatan kelas mendatang',
            'content' => 'Mohon seluruh peserta mempersiapkan perlengkapan yang diperlukan.',
            'target_scope' => 'SCHOOL', 'target_roles' => ['student', 'parent'],
            ...$changes,
        ];
    }

    public function test_guest_redirected_and_unpermissioned_user_forbidden(): void
    {
        $this->get('/announcements')->assertRedirect('/login');
        $user = User::query()->create([
            'name' => 'No announcement access', 'username' => $this->httpToken('nomsg'),
            'email' => $this->httpToken('nomsg').'@example.test',
            'password' => 'password', 'is_active' => true,
        ]);
        $this->actingAs($user, 'web')->get('/announcements')->assertForbidden();
    }

    public function test_student_feed_only_shows_matching_role_and_hides_mine_and_creation(): void
    {
        [$student] = $this->createApiStudent();
        $principal = $this->createApiPrincipal();
        $visible = $this->published($principal, ['student']);
        $hidden = $this->published($principal, ['parent']);
        $this->actingAs($student, 'web');
        $this->inertiaGet('/announcements')->assertOk()
            ->assertJsonPath('component', 'Announcements/Index')
            ->assertJsonPath('props.announcements.records.total', 1)
            ->assertJsonPath('props.announcements.records.data.0.uuid', $visible->uuid)
            ->assertJsonPath('props.announcements.can.manage', false)
            ->assertJsonPath('props.announcements.can.create', false)
            ->assertDontSee($hidden->uuid);
        $this->get('/announcements?tab=mine')->assertForbidden();
        $this->get('/announcements/create')->assertForbidden();
        $this->post('/announcements', $this->draftPayload())->assertForbidden();
        $this->get('/announcements/'.$hidden->uuid)->assertNotFound();
    }

    public function test_student_and_linked_parent_see_own_class_but_foreign_student_cannot(): void
    {
        [$studentAUser, $studentA] = $this->createApiStudent();
        [$studentBUser, $studentB] = $this->createApiStudent();
        [$parent, $guardian] = $this->createApiParent();
        $this->linkApiParentToStudent($guardian, $studentA);
        $year = $this->createApiAcademicYear();
        $classA = $this->createApiClass($year);
        $classB = $this->createApiClass($year);
        $this->enrollApiStudent($studentA, $classA);
        $this->enrollApiStudent($studentB, $classB);
        $announcement = $this->published($this->createApiPrincipal(), ['student','parent'],
            AnnouncementTargetScope::Classroom, $classA);
        $this->actingAs($studentAUser, 'web');
        $this->inertiaGet('/announcements')->assertJsonPath('props.announcements.records.total', 1);
        $this->inertiaGet('/announcements/'.$announcement->uuid)
            ->assertJsonPath('props.announcement.uuid', $announcement->uuid)
            ->assertJsonPath('props.announcement.target_roles', null); // role metadata is editor-only
        $this->actingAs($parent, 'web');
        $this->inertiaGet('/announcements')->assertJsonPath('props.announcements.records.total', 1);
        $this->actingAs($studentBUser, 'web');
        $this->inertiaGet('/announcements')->assertJsonPath('props.announcements.records.total', 0);
        $this->get('/announcements/'.$announcement->uuid)->assertNotFound();
    }

    public function test_principal_creates_school_draft_and_only_mine_shows_it(): void
    {
        $principal = $this->createApiPrincipal();
        [$student] = $this->createApiStudent();
        $this->actingAs($principal, 'web');
        $this->inertiaGet('/announcements/create')
            ->assertJsonPath('component', 'Announcements/Form')
            ->assertJsonPath('props.editor.options.can_school', true);
        $this->post('/announcements', $this->draftPayload())->assertRedirect()
            ->assertSessionHas('success');
        $draft = Announcement::query()->firstOrFail();
        $this->assertSame(AnnouncementStatus::Draft, $draft->status);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'announcement', 'action' => 'CREATE', 'user_id' => $principal->id,
        ]);
        $this->inertiaGet('/announcements?tab=mine')->assertJsonPath('props.announcements.records.total', 1)
            ->assertJsonPath('props.announcements.records.data.0.uuid', $draft->uuid)
            ->assertJsonPath('props.announcements.records.data.0.can.update', true);
        $this->actingAs($student, 'web');
        $this->inertiaGet('/announcements')->assertJsonPath('props.announcements.records.total', 0);
        $this->get('/announcements/'.$draft->uuid)->assertNotFound();
    }

    public function test_teacher_only_receives_own_homeroom_class_options_and_cannot_target_other_class(): void
    {
        [$teacherUser, $teacher] = $this->createApiTeacher();
        [$otherUser, $otherTeacher] = $this->createApiTeacher();
        $year = $this->createApiAcademicYear();
        $own = $this->createApiClass($year, $teacher);
        $foreign = $this->createApiClass($year, $otherTeacher);
        $this->actingAs($teacherUser, 'web');
        $this->inertiaGet('/announcements/create')->assertOk()
            ->assertJsonPath('props.editor.options.can_school', false)
            ->assertJsonPath('props.editor.options.classes.0.uuid', $own->uuid)
            ->assertDontSee($foreign->uuid);
        $this->post('/announcements', $this->draftPayload([
            'target_scope' => 'CLASS', 'class_uuid' => $foreign->uuid,
        ]))->assertForbidden();
        $this->post('/announcements', $this->draftPayload([
            'target_scope' => 'SCHOOL',
        ]))->assertForbidden();
        $this->assertDatabaseCount('announcements', 0);
        $this->post('/announcements', $this->draftPayload([
            'target_scope' => 'CLASS', 'class_uuid' => $own->uuid,
        ]))->assertRedirect()->assertSessionHas('success');
        $draft = Announcement::query()->firstOrFail();
        $this->actingAs($otherUser, 'web')->get('/announcements/'.$draft->uuid)->assertNotFound();
    }

    public function test_principal_cannot_create_class_without_permission(): void
    {
        $principal = $this->createApiPrincipal();
        $class = $this->createApiClass($this->createApiAcademicYear());
        $this->actingAs($principal, 'web')->post('/announcements', $this->draftPayload([
            'target_scope' => 'CLASS', 'class_uuid' => $class->uuid,
        ]))->assertForbidden();
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_owner_can_edit_draft_and_other_owner_cannot(): void
    {
        $principal = $this->createApiPrincipal();
        $another = $this->createApiPrincipal();
        $this->actingAs($principal, 'web')->post('/announcements', $this->draftPayload())->assertRedirect();
        $draft = Announcement::query()->firstOrFail();
        $this->inertiaGet('/announcements/'.$draft->uuid.'/edit')
            ->assertJsonPath('component', 'Announcements/Form')
            ->assertJsonPath('props.editor.announcement.uuid', $draft->uuid)
            ->assertJsonPath('props.editor.announcement.can.update', true);
        $this->patch('/announcements/'.$draft->uuid, [
            'title' => 'Agenda diperbarui untuk seluruh siswa', 'target_roles' => ['student'],
        ])->assertRedirect('/announcements/'.$draft->uuid);
        $this->assertSame('Agenda diperbarui untuk seluruh siswa', $draft->fresh()->title);
        $this->assertSame(['student'], $draft->fresh()->roles->pluck('name')->all());
        $this->actingAs($another, 'web');
        $this->get('/announcements/'.$draft->uuid)->assertNotFound();
        $this->get('/announcements/'.$draft->uuid.'/edit')->assertForbidden();
        $this->patch('/announcements/'.$draft->uuid, ['title' => 'Invalid foreign edit'])->assertForbidden();
    }

    public function test_scheduled_publish_hidden_until_time_then_expired_in_feed(): void
    {
        $principal = $this->createApiPrincipal();
        [$student] = $this->createApiStudent();
        $this->actingAs($principal, 'web')->post('/announcements', $this->draftPayload())->assertRedirect();
        $draft = Announcement::query()->firstOrFail();
        $this->post('/announcements/'.$draft->uuid.'/publish', [
            'publish_at' => '2026-09-22T08:00', 'expired_at' => '2026-09-23T08:00',
        ])->assertRedirect('/announcements/'.$draft->uuid)->assertSessionHas('success');
        $this->assertSame(AnnouncementStatus::Published, $draft->fresh()->status);
        $this->inertiaGet('/announcements?tab=mine')->assertJsonPath('props.announcements.records.data.0.visibility', 'scheduled');
        $this->actingAs($student, 'web');
        $this->inertiaGet('/announcements')->assertJsonPath('props.announcements.records.total', 0);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-22 08:01:00', 'Asia/Jakarta'));
        $this->inertiaGet('/announcements')->assertJsonPath('props.announcements.records.total', 1);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-23 08:01:00', 'Asia/Jakarta'));
        $this->inertiaGet('/announcements')->assertJsonPath('props.announcements.records.total', 0);
    }

    public function test_publishing_now_archiving_and_repeated_mutations_obey_policy(): void
    {
        $principal = $this->createApiPrincipal();
        [$student] = $this->createApiStudent();
        $this->actingAs($principal, 'web')->post('/announcements', $this->draftPayload())->assertRedirect();
        $draft = Announcement::query()->firstOrFail();
        $this->post('/announcements/'.$draft->uuid.'/publish', [])->assertRedirect('/announcements/'.$draft->uuid);
        $this->assertSame(AnnouncementStatus::Published, $draft->fresh()->status);
        $this->patch('/announcements/'.$draft->uuid, ['title' => 'Salah edit sesudah terbit'])->assertForbidden();
        $this->post('/announcements/'.$draft->uuid.'/publish', [])->assertForbidden();
        $this->actingAs($student, 'web');
        $this->inertiaGet('/announcements')->assertJsonPath('props.announcements.records.total', 1);
        $this->actingAs($principal, 'web');
        $this->post('/announcements/'.$draft->uuid.'/archive')->assertRedirect('/announcements/'.$draft->uuid)
            ->assertSessionHas('success');
        $this->assertSame(AnnouncementStatus::Archived, $draft->fresh()->status);
        $this->post('/announcements/'.$draft->uuid.'/archive')->assertForbidden();
        $this->actingAs($student, 'web');
        $this->inertiaGet('/announcements')->assertJsonPath('props.announcements.records.total', 0);
        $this->get('/announcements/'.$draft->uuid)->assertNotFound();
    }

    public function test_invalid_payloads_unknown_roles_and_publish_expiry_are_rejected(): void
    {
        $principal = $this->createApiPrincipal();
        $this->actingAs($principal, 'web');
        $this->from('/announcements/create')->post('/announcements', $this->draftPayload([
            'title' => 'A', 'target_roles' => [],
        ]))->assertRedirect('/announcements/create')->assertSessionHasErrors(['title', 'target_roles']);
        $this->assertDatabaseCount('announcements', 0);
        $this->from('/announcements/create')->post('/announcements', $this->draftPayload([
            'target_roles' => ['unknown-role-portal'],
        ]))->assertSessionHasErrors('target_roles');
        $this->assertDatabaseCount('announcements', 0);
        $this->post('/announcements', $this->draftPayload())->assertRedirect();
        $draft = Announcement::query()->firstOrFail();
        $this->from('/announcements/'.$draft->uuid)->post('/announcements/'.$draft->uuid.'/publish', [
            'publish_at' => '2026-09-24T08:00', 'expired_at' => '2026-09-23T08:00',
        ])->assertSessionHasErrors('expired_at');
        $this->assertSame(AnnouncementStatus::Draft, $draft->fresh()->status);
    }

    public function test_mine_filters_are_scoped_and_feed_rejects_status_filter(): void
    {
        $principal = $this->createApiPrincipal();
        $other = $this->createApiPrincipal();
        $this->actingAs($principal, 'web')->post('/announcements', $this->draftPayload())->assertRedirect();
        $own = Announcement::query()->firstOrFail();
        $this->actingAs($other, 'web')->post('/announcements', $this->draftPayload(['title' => 'Informasi rapat untuk wali']))->assertRedirect();
        $foreign = Announcement::query()->where('id', '!=', $own->id)->firstOrFail();
        $this->actingAs($principal, 'web');
        $this->inertiaGet('/announcements?tab=mine&status=DRAFT')->assertJsonPath('props.announcements.records.total', 1)
            ->assertJsonPath('props.announcements.counts.draft', 1)
            ->assertDontSee($foreign->uuid);
        $this->get('/announcements?tab=mine&status=HACKED')->assertSessionHasErrors('status');
        $this->get('/announcements?status=DRAFT')->assertSessionHasErrors('status');
        $this->get('/announcements?per_page=999')->assertSessionHasErrors('per_page');
    }

    public function test_feed_and_detail_only_serialize_allowlisted_data(): void
    {
        [$student] = $this->createApiStudent();
        $principal = $this->createApiPrincipal();
        $announcement = $this->published($principal, ['student']);
        $this->actingAs($student, 'web');
        $this->inertiaGet('/announcements')->assertJsonMissingPath('props.announcements.records.data.0.id')
            ->assertJsonMissingPath('props.announcements.records.data.0.content')
            ->assertJsonMissingPath('props.announcements.records.data.0.target_roles');
        $this->inertiaGet('/announcements/'.$announcement->uuid)
            ->assertJsonPath('props.announcement.content', 'Pengumuman kegiatan dengan informasi waktu dan tempat.')
            ->assertJsonPath('props.announcement.target_roles', null)
            ->assertJsonMissingPath('props.announcement.id')
            ->assertJsonMissingPath('props.announcement.created_by');
    }
}

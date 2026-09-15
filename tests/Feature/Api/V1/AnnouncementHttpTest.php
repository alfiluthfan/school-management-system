<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Communication\AnnouncementStatus;
use App\Enums\Communication\AnnouncementTargetScope;
use App\Models\Academic\SchoolClass;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Communication\Announcement;
use Carbon\CarbonImmutable;

class AnnouncementHttpTest extends HttpApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-09-15 10:00:00',
                'Asia/Jakarta'
            )
        );
    }

    public function test_guest_gets_401_for_announcement_feed(): void
    {
        $this->getJson(
            route(
                'api.v1.announcements.index'
            )
        )->assertUnauthorized();
    }

    public function test_principal_can_create_school_draft(): void
    {
        $principal =
            $this->createApiPrincipal();

        $response = $this
            ->actingAs($principal)
            ->postJson(
                route(
                    'api.v1.announcements.store'
                ),
                [
                    'title' =>
                    'Informasi Kegiatan Sekolah',
                    'content' =>
                    'Kegiatan sekolah akan dilaksanakan pada akhir pekan ini.',
                    'target_scope' =>
                    'SCHOOL',
                    'target_roles' => [
                        'student',
                        'parent',
                    ],
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.target_scope.value',
                'SCHOOL'
            )
            ->assertJsonPath(
                'data.status.value',
                'DRAFT'
            );

        $uuid =
            $response->json('data.uuid');

        $this->assertDatabaseHas(
            'announcements',
            [
                'uuid' => $uuid,
                'created_by' =>
                $principal->id,
                'status' => 'DRAFT',
                'class_id' => null,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' =>
                $principal->id,
                'module' =>
                'announcement',
                'action' =>
                'CREATE',
            ]
        );
    }

    public function test_teacher_cannot_create_school_announcement(): void
    {
        [$teacherUser] =
            $this->createApiTeacher();

        $this->actingAs($teacherUser)
            ->postJson(
                route(
                    'api.v1.announcements.store'
                ),
                [
                    'title' =>
                    'Pengumuman Sekolah',
                    'content' =>
                    'Guru mencoba membuat pengumuman tingkat sekolah.',
                    'target_scope' =>
                    'SCHOOL',
                    'target_roles' => [
                        'student',
                    ],
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'announcements',
            0
        );
    }

    public function test_homeroom_teacher_can_create_class_draft_but_other_teacher_cannot(): void
    {
        [$teacherUser, $teacher] =
            $this->createApiTeacher();

        [$otherTeacherUser] =
            $this->createApiTeacher();

        $year =
            $this->createApiAcademicYear();

        $class =
            $this->createApiClass(
                $year,
                $teacher
            );

        $payload = [
            'title' =>
            'Pengumuman Kelas',
            'content' =>
            'Besok siswa diminta membawa perlengkapan belajar tambahan.',
            'target_scope' =>
            'CLASS',
            'class_uuid' =>
            $class->uuid,
            'target_roles' => [
                'student',
                'parent',
            ],
        ];

        $this->actingAs($teacherUser)
            ->postJson(
                route(
                    'api.v1.announcements.store'
                ),
                $payload
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.school_class.uuid',
                $class->uuid
            );

        $this->actingAs(
            $otherTeacherUser
        )
            ->postJson(
                route(
                    'api.v1.announcements.store'
                ),
                [
                    ...$payload,
                    'title' =>
                    'Kelas Orang Lain',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'announcements',
            1
        );
    }

    public function test_student_feed_respects_target_role(): void
    {
        [$studentUser] =
            $this->createApiStudent();

        $studentAnnouncement =
            $this->createPublishedAnnouncement(
                creator: $this->createApiPrincipal(),
                scope: AnnouncementTargetScope::School,
                targetRoles: [
                    'student',
                ],
                title: 'Untuk Siswa'
            );

        $parentAnnouncement =
            $this->createPublishedAnnouncement(
                creator: $this->createApiAdmin(),
                scope: AnnouncementTargetScope::School,
                targetRoles: [
                    'parent',
                ],
                title: 'Untuk Orang Tua'
            );

        $this->actingAs($studentUser)
            ->getJson(
                route(
                    'api.v1.announcements.index'
                )
            )
            ->assertOk()
            ->assertJsonFragment([
                'uuid' =>
                $studentAnnouncement->uuid,
            ])
            ->assertJsonMissing([
                'uuid' =>
                $parentAnnouncement->uuid,
            ]);
    }

    public function test_class_announcement_only_reaches_students_in_that_class(): void
    {
        $principal =
            $this->createApiPrincipal();

        [, $studentA] =
            $this->createApiStudent();

        [$studentBUser, $studentB] =
            $this->createApiStudent();

        $studentAUser =
            $studentA->user;

        $year =
            $this->createApiAcademicYear();

        $classA =
            $this->createApiClass($year);

        $classB =
            $this->createApiClass($year);

        $this->enrollApiStudent(
            $studentA,
            $classA
        );

        $this->enrollApiStudent(
            $studentB,
            $classB
        );

        $announcement =
            $this->createPublishedAnnouncement(
                creator: $principal,
                scope: AnnouncementTargetScope::Classroom,
                targetRoles: [
                    'student',
                ],
                schoolClass: $classA,
                title: 'Khusus Kelas A'
            );

        $this->actingAs(
            $studentAUser
        )
            ->getJson(
                route(
                    'api.v1.announcements.index'
                )
            )
            ->assertOk()
            ->assertJsonFragment([
                'uuid' =>
                $announcement->uuid,
            ]);

        $this->actingAs(
            $studentBUser
        )
            ->getJson(
                route(
                    'api.v1.announcements.index'
                )
            )
            ->assertOk()
            ->assertJsonMissing([
                'uuid' =>
                $announcement->uuid,
            ]);

        $this->actingAs(
            $studentBUser
        )
            ->getJson(
                route(
                    'api.v1.announcements.show',
                    $announcement
                )
            )
            ->assertNotFound();
    }

    public function test_parent_receives_class_announcement_through_linked_child(): void
    {
        [$parentUser, $guardian] =
            $this->createApiParent();

        [, $student] =
            $this->createApiStudent();

        $this->linkApiParentToStudent(
            $guardian,
            $student
        );

        $year =
            $this->createApiAcademicYear();

        $class =
            $this->createApiClass($year);

        $this->enrollApiStudent(
            $student,
            $class
        );

        $announcement =
            $this->createPublishedAnnouncement(
                creator: $this->createApiPrincipal(),
                scope: AnnouncementTargetScope::Classroom,
                targetRoles: [
                    'parent',
                ],
                schoolClass: $class,
                title: 'Informasi Orang Tua Kelas'
            );

        $this->actingAs($parentUser)
            ->getJson(
                route(
                    'api.v1.announcements.index'
                )
            )
            ->assertOk()
            ->assertJsonFragment([
                'uuid' =>
                $announcement->uuid,
            ]);
    }

    public function test_future_published_announcement_is_hidden_until_publish_time(): void
    {
        $principal =
            $this->createApiPrincipal();

        [$studentUser] =
            $this->createApiStudent();

        $draft =
            $this->createDraftViaApi(
                $principal,
                targetRoles: [
                    'student',
                ]
            );

        $this->actingAs($principal)
            ->postJson(
                route(
                    'api.v1.announcements.publish',
                    $draft
                ),
                [
                    'publish_at' =>
                    '2026-09-16 08:00:00',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status.value',
                'PUBLISHED'
            );

        $this->actingAs($studentUser)
            ->getJson(
                route(
                    'api.v1.announcements.index'
                )
            )
            ->assertOk()
            ->assertJsonMissing([
                'uuid' =>
                $draft->uuid,
            ]);

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-09-16 08:01:00',
                'Asia/Jakarta'
            )
        );

        $this->actingAs($studentUser)
            ->getJson(
                route(
                    'api.v1.announcements.index'
                )
            )
            ->assertOk()
            ->assertJsonFragment([
                'uuid' =>
                $draft->uuid,
            ]);
    }

    public function test_expired_announcement_is_hidden_from_feed(): void
    {
        [$studentUser] =
            $this->createApiStudent();

        $announcement =
            $this->createPublishedAnnouncement(
                creator: $this->createApiPrincipal(),
                scope: AnnouncementTargetScope::School,
                targetRoles: [
                    'student',
                ],
                title: 'Pengumuman Sementara',
                expiredAt: '2026-09-15 11:00:00'
            );

        $this->actingAs($studentUser)
            ->getJson(
                route(
                    'api.v1.announcements.index'
                )
            )
            ->assertOk()
            ->assertJsonFragment([
                'uuid' =>
                $announcement->uuid,
            ]);

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-09-15 11:01:00',
                'Asia/Jakarta'
            )
        );

        $this->actingAs($studentUser)
            ->getJson(
                route(
                    'api.v1.announcements.index'
                )
            )
            ->assertOk()
            ->assertJsonMissing([
                'uuid' =>
                $announcement->uuid,
            ]);
    }

    public function test_creator_can_update_draft_and_mine_lists_it(): void
    {
        $principal =
            $this->createApiPrincipal();

        $draft =
            $this->createDraftViaApi(
                $principal,
                targetRoles: [
                    'student',
                ]
            );

        $this->actingAs($principal)
            ->patchJson(
                route(
                    'api.v1.announcements.update',
                    $draft
                ),
                [
                    'title' =>
                    'Judul Pengumuman Diperbarui',
                    'target_roles' => [
                        'student',
                        'parent',
                    ],
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'Judul Pengumuman Diperbarui'
            );

        $this->actingAs($principal)
            ->getJson(
                route(
                    'api.v1.announcements.mine',
                    [
                        'status' =>
                        'DRAFT',
                    ]
                )
            )
            ->assertOk()
            ->assertJsonFragment([
                'uuid' =>
                $draft->uuid,
            ]);
    }

    public function test_published_announcement_cannot_be_updated(): void
    {
        $principal =
            $this->createApiPrincipal();

        $draft =
            $this->createDraftViaApi(
                $principal,
                targetRoles: [
                    'student',
                ]
            );

        $this->actingAs($principal)
            ->postJson(
                route(
                    'api.v1.announcements.publish',
                    $draft
                )
            )
            ->assertOk();

        $this->actingAs($principal)
            ->patchJson(
                route(
                    'api.v1.announcements.update',
                    $draft
                ),
                [
                    'title' =>
                    'Tidak Boleh Diubah',
                ]
            )
            ->assertForbidden();
    }

    public function test_archive_removes_published_announcement_from_feed(): void
    {
        $principal =
            $this->createApiPrincipal();

        [$studentUser] =
            $this->createApiStudent();

        $draft =
            $this->createDraftViaApi(
                $principal,
                targetRoles: [
                    'student',
                ]
            );

        $this->actingAs($principal)
            ->postJson(
                route(
                    'api.v1.announcements.publish',
                    $draft
                )
            )
            ->assertOk();

        $this->actingAs($studentUser)
            ->getJson(
                route(
                    'api.v1.announcements.index'
                )
            )
            ->assertJsonFragment([
                'uuid' =>
                $draft->uuid,
            ]);

        $this->actingAs($principal)
            ->postJson(
                route(
                    'api.v1.announcements.archive',
                    $draft
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status.value',
                'ARCHIVED'
            );

        $this->actingAs($studentUser)
            ->getJson(
                route(
                    'api.v1.announcements.index'
                )
            )
            ->assertJsonMissing([
                'uuid' =>
                $draft->uuid,
            ]);

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' =>
                $principal->id,
                'module' =>
                'announcement',
                'action' =>
                'ARCHIVE',
            ]
        );
    }

    private function createDraftViaApi(
        User $creator,
        array $targetRoles
    ): Announcement {
        $response = $this
            ->actingAs($creator)
            ->postJson(
                route(
                    'api.v1.announcements.store'
                ),
                [
                    'title' =>
                    'Draft Pengumuman Test',
                    'content' =>
                    'Isi draft pengumuman yang cukup panjang untuk validasi.',
                    'target_scope' =>
                    'SCHOOL',
                    'target_roles' =>
                    $targetRoles,
                ]
            )
            ->assertCreated();

        return Announcement::query()
            ->where(
                'uuid',
                $response->json('data.uuid')
            )
            ->firstOrFail();
    }

    private function createPublishedAnnouncement(
        User $creator,
        AnnouncementTargetScope $scope,
        array $targetRoles,
        string $title,
        ?SchoolClass $schoolClass = null,
        ?string $expiredAt = null
    ): Announcement {
        $announcement =
            Announcement::query()->create([
                'created_by' =>
                $creator->id,
                'class_id' =>
                $schoolClass?->id,
                'title' =>
                $title,
                'content' =>
                'Isi pengumuman untuk pengujian audience announcement.',
                'target_scope' =>
                $scope,
                'publish_at' =>
                CarbonImmutable::now(
                    'Asia/Jakarta'
                ),
                'expired_at' =>
                $expiredAt,
                'status' =>
                AnnouncementStatus::Published,
            ]);

        $roleIds = Role::query()
            ->whereIn(
                'name',
                $targetRoles
            )
            ->pluck('id');

        $announcement->roles()->sync(
            $roleIds
        );

        return $announcement->fresh([
            'creator',
            'schoolClass',
            'roles',
        ]);
    }
}

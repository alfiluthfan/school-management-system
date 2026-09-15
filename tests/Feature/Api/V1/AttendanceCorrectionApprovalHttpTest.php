<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\System\ApprovalStatus;
use App\Models\Academic\Teacher;
use App\Models\Attendance\StudentAttendance;
use App\Models\Auth\User;
use App\Models\System\Approval;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Concerns\BuildsHttpApiFixtures;
use Tests\TestCase;

class AttendanceCorrectionApprovalHttpTest extends TestCase
{
    use BuildsHttpApiFixtures;
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Asia/Jakarta',
        ]);

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    public function test_admin_can_submit_correction_without_mutating_attendance(): void
    {
        $admin = $this->createApiAdmin();

        [, $attendance] =
            $this->makeAttendanceWithSchedule();

        $originalCheckIn =
            $attendance->check_in_at->toIso8601String();

        $response = $this->actingAs($admin)
            ->postJson(
                route(
                    'api.v1.student-attendances'
                    .'.correction-requests.store',
                    $attendance
                ),
                [
                    'reason' =>
                        'Jam check-in pada perangkat tercatat tidak tepat.',
                    'changes' => [
                        'check_in_at' =>
                            '2026-09-14 07:20:00',
                        'notes' =>
                            'Dikoreksi berdasarkan catatan piket.',
                    ],
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.module.value',
                'ATTENDANCE'
            )
            ->assertJsonPath(
                'data.action.value',
                'CORRECTION'
            )
            ->assertJsonPath(
                'data.status.value',
                'PENDING'
            )
            ->assertJsonPath(
                'data.request_payload.changes.check_in_at',
                '2026-09-14 07:20:00'
            );

        $attendance = $attendance->fresh();

        $this->assertSame(
            AttendanceStatus::Present,
            $attendance->status
        );
        $this->assertSame(
            $originalCheckIn,
            $attendance->check_in_at
                ->toIso8601String()
        );
        $this->assertNull(
            $attendance->corrected_by
        );

        $this->assertDatabaseHas('approvals', [
            'uuid' => $response->json('data.uuid'),
            'status' => 'PENDING',
            'module' => 'ATTENDANCE',
            'action' => 'CORRECTION',
        ]);
    }

    public function test_homeroom_teacher_can_submit_correction_for_own_class(): void
    {
        [$teacherUser, $teacher] =
            $this->createApiTeacher();

        [, $attendance] =
            $this->makeAttendanceWithSchedule(
                $teacher
            );

        $this->actingAs($teacherUser)
            ->postJson(
                route(
                    'api.v1.student-attendances'
                    .'.correction-requests.store',
                    $attendance
                ),
                [
                    'reason' =>
                        'Absensi perlu diperbaiki sesuai catatan wali kelas.',
                    'changes' => [
                        'notes' =>
                            'Verifikasi wali kelas.',
                    ],
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.status.value',
                'PENDING'
            );
    }

    public function test_teacher_cannot_submit_correction_for_another_class(): void
    {
        [$teacherUser] =
            $this->createApiTeacher();

        [, $otherTeacher] =
            $this->createApiTeacher();

        [, $attendance] =
            $this->makeAttendanceWithSchedule(
                $otherTeacher
            );

        $this->actingAs($teacherUser)
            ->postJson(
                route(
                    'api.v1.student-attendances'
                    .'.correction-requests.store',
                    $attendance
                ),
                [
                    'reason' =>
                        'Mencoba koreksi kelas lain.',
                    'changes' => [
                        'notes' => 'Tidak boleh.',
                    ],
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'approvals',
            0
        );
    }

    public function test_client_cannot_submit_derived_or_protected_attendance_fields(): void
    {
        $admin = $this->createApiAdmin();

        [, $attendance] =
            $this->makeAttendanceWithSchedule();

        $this->actingAs($admin)
            ->postJson(
                route(
                    'api.v1.student-attendances'
                    .'.correction-requests.store',
                    $attendance
                ),
                [
                    'reason' =>
                        'Mencoba mengirim derived field.',
                    'changes' => [
                        'late_minutes' => 999,
                    ],
                ]
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'approvals',
            0
        );
    }

    public function test_duplicate_pending_correction_is_rejected(): void
    {
        $admin = $this->createApiAdmin();

        [, $attendance] =
            $this->makeAttendanceWithSchedule();

        $url = route(
            'api.v1.student-attendances'
            .'.correction-requests.store',
            $attendance
        );

        $payload = [
            'reason' =>
                'Koreksi jam absensi.',
            'changes' => [
                'check_in_at' =>
                    '2026-09-14 07:10:00',
            ],
        ];

        $this->actingAs($admin)
            ->postJson($url, $payload)
            ->assertCreated();

        $this->actingAs($admin)
            ->postJson($url, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'approval'
            );

        $this->assertSame(
            1,
            Approval::query()->count()
        );
    }

    public function test_principal_approval_recalculates_status_and_late_minutes(): void
    {
        $admin = $this->createApiAdmin();
        $principal =
            $this->createApiPrincipal();

        [, $attendance] =
            $this->makeAttendanceWithSchedule();

        $approval = $this->submitCorrection(
            requester: $admin,
            attendance: $attendance,
            reason:
                'Jam check-in sebenarnya pukul 07:20.',
            changes: [
                'check_in_at' =>
                    '2026-09-14 07:20:00',
                /*
                 * Even if a client requests PRESENT,
                 * backend schedule calculation wins.
                 */
                'status' => 'PRESENT',
            ]
        );

        $this->actingAs($principal)
            ->postJson(
                route(
                    'api.v1.approvals.approve',
                    $approval
                ),
                [
                    'review_notes' =>
                        'Catatan piket sudah diverifikasi.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status.value',
                'APPROVED'
            );

        $attendance = $attendance->fresh();

        $this->assertSame(
            AttendanceStatus::Late,
            $attendance->status
        );
        $this->assertSame(
            20,
            $attendance->late_minutes
        );
        $this->assertSame(
            $principal->id,
            $attendance->corrected_by
        );
        $this->assertSame(
            'Jam check-in sebenarnya pukul 07:20.',
            $attendance->correction_reason
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'user_id' => $principal->id,
                'module' => 'attendance',
                'action' => 'CORRECTION',
                'entity_id' =>
                    $attendance->id,
            ]
        );
    }

    public function test_approved_non_presence_status_clears_times_and_late_minutes(): void
    {
        $admin = $this->createApiAdmin();
        $principal =
            $this->createApiPrincipal();

        [, $attendance] =
            $this->makeAttendanceWithSchedule();

        $attendance->forceFill([
            'status' => AttendanceStatus::Late,
            'late_minutes' => 20,
            'check_in_at' =>
                '2026-09-14 07:20:00',
            'check_out_at' =>
                '2026-09-14 15:00:00',
        ])->save();

        $approval = $this->submitCorrection(
            requester: $admin,
            attendance: $attendance,
            reason:
                'Siswa sebenarnya sakit dan tidak hadir.',
            changes: [
                'status' => 'SICK',
            ]
        );

        $this->actingAs($principal)
            ->postJson(
                route(
                    'api.v1.approvals.approve',
                    $approval
                )
            )
            ->assertOk();

        $attendance = $attendance->fresh();

        $this->assertSame(
            AttendanceStatus::Sick,
            $attendance->status
        );
        $this->assertNull(
            $attendance->check_in_at
        );
        $this->assertNull(
            $attendance->check_out_at
        );
        $this->assertSame(
            0,
            $attendance->late_minutes
        );
    }

    public function test_rejected_correction_does_not_mutate_attendance_and_can_be_resubmitted(): void
    {
        $admin = $this->createApiAdmin();
        $principal =
            $this->createApiPrincipal();

        [, $attendance] =
            $this->makeAttendanceWithSchedule();

        $approval = $this->submitCorrection(
            requester: $admin,
            attendance: $attendance,
            reason:
                'Jam check-in perlu dikoreksi.',
            changes: [
                'check_in_at' =>
                    '2026-09-14 07:20:00',
            ]
        );

        $this->actingAs($principal)
            ->postJson(
                route(
                    'api.v1.approvals.reject',
                    $approval
                ),
                [
                    'review_notes' =>
                        'Bukti koreksi belum cukup.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status.value',
                'REJECTED'
            );

        $attendance = $attendance->fresh();

        $this->assertSame(
            AttendanceStatus::Present,
            $attendance->status
        );
        $this->assertSame(
            '06:55:00',
            $attendance->check_in_at
                ->format('H:i:s')
        );
        $this->assertNull(
            $attendance->corrected_by
        );

        /*
         * Rejection releases pending_key.
         */
        $this->actingAs($admin)
            ->postJson(
                route(
                    'api.v1.student-attendances'
                    .'.correction-requests.store',
                    $attendance
                ),
                [
                    'reason' =>
                        'Pengajuan ulang dengan bukti lengkap.',
                    'changes' => [
                        'check_in_at' =>
                            '2026-09-14 07:15:00',
                    ],
                ]
            )
            ->assertCreated();

        $this->assertSame(
            2,
            Approval::query()->count()
        );
    }

    /**
     * @return array{0: \App\Models\Academic\Student, 1: StudentAttendance}
     */
    private function makeAttendanceWithSchedule(
        ?Teacher $homeroomTeacher = null
    ): array {
        [, $student] =
            $this->createApiStudent();

        $year =
            $this->createApiAcademicYear();

        $class = $this->createApiClass(
            $year,
            $homeroomTeacher
        );

        $this->enrollApiStudent(
            $student,
            $class
        );

        $location =
            $this->createApiSchoolLocation();

        $schedule =
            $this->createApiStudentSchedule(
                $year,
                $location
            );

        $attendance =
            $this->createApiAttendance(
                $student,
                $class,
                '2026-09-14'
            );

        $attendance->update([
            'attendance_schedule_id' =>
                $schedule->id,
            'school_location_id' =>
                $location->id,
        ]);

        return [
            $student,
            $attendance->fresh(),
        ];
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function submitCorrection(
        User $requester,
        StudentAttendance $attendance,
        string $reason,
        array $changes
    ): Approval {
        $response = $this
            ->actingAs($requester)
            ->postJson(
                route(
                    'api.v1.student-attendances'
                    .'.correction-requests.store',
                    $attendance
                ),
                [
                    'reason' => $reason,
                    'changes' => $changes,
                ]
            )
            ->assertCreated();

        return Approval::query()
            ->where(
                'uuid',
                $response->json('data.uuid')
            )
            ->firstOrFail();
    }
}

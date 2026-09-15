<?php

namespace App\Actions\Announcements;

use App\Enums\Communication\AnnouncementStatus;
use App\Enums\Communication\AnnouncementTargetScope;
use App\Models\Academic\SchoolClass;
use App\Models\Auth\User;
use App\Models\Communication\Announcement;
use App\Services\Announcements\AnnouncementTargetRoleResolver;
use App\Services\System\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateAnnouncementAction
{
    public function __construct(
        private readonly AnnouncementTargetRoleResolver $roleResolver,
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * @param array<int, string> $targetRoles
     */
    public function execute(
        User $actor,
        string $title,
        string $content,
        AnnouncementTargetScope $targetScope,
        ?SchoolClass $schoolClass,
        array $targetRoles,
        ?CarbonImmutable $expiredAt = null
    ): Announcement {
        $this->assertTargetConsistency(
            $targetScope,
            $schoolClass
        );

        $roles = $this->roleResolver
            ->resolve($targetRoles);

        return DB::transaction(function () use (
            $actor,
            $title,
            $content,
            $targetScope,
            $schoolClass,
            $roles,
            $expiredAt
        ): Announcement {
            $announcement =
                Announcement::query()->create([
                    'created_by' =>
                    $actor->id,
                    'class_id' =>
                    $schoolClass?->id,
                    'title' =>
                    $title,
                    'content' =>
                    $content,
                    'target_scope' =>
                    $targetScope,
                    'publish_at' =>
                    null,
                    'expired_at' =>
                    $expiredAt,
                    'status' =>
                    AnnouncementStatus::Draft,
                ]);

            $announcement->roles()->sync(
                $roles->modelKeys()
            );

            $this->auditLogger->log(
                actor: $actor,
                module: 'announcement',
                action: 'CREATE',
                entity: $announcement,
                oldValues: null,
                newValues: [
                    'title' =>
                    $announcement->title,
                    'target_scope' =>
                    $targetScope->value,
                    'class_id' =>
                    $schoolClass?->id,
                    'target_roles' =>
                    $roles->pluck('name')
                        ->values()
                        ->all(),
                    'status' =>
                    AnnouncementStatus::Draft->value,
                ]
            );

            return $announcement->fresh([
                'creator',
                'schoolClass',
                'roles',
            ]);
        }, 3);
    }

    private function assertTargetConsistency(
        AnnouncementTargetScope $scope,
        ?SchoolClass $schoolClass
    ): void {
        if (
            $scope
            === AnnouncementTargetScope::School
            && $schoolClass !== null
        ) {
            throw ValidationException::withMessages([
                'class_uuid' =>
                'Pengumuman tingkat sekolah tidak boleh memiliki target kelas.',
            ]);
        }

        if (
            $scope
            === AnnouncementTargetScope::Classroom
            && $schoolClass === null
        ) {
            throw ValidationException::withMessages([
                'class_uuid' =>
                'Pengumuman tingkat kelas membutuhkan class_uuid.',
            ]);
        }
    }
}

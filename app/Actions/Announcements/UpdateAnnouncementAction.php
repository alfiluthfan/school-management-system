<?php

namespace App\Actions\Announcements;

use App\Enums\Communication\AnnouncementStatus;
use App\Models\Auth\User;
use App\Models\Communication\Announcement;
use App\Services\Announcements\AnnouncementTargetRoleResolver;
use App\Services\System\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateAnnouncementAction
{
    public function __construct(
        private readonly AnnouncementTargetRoleResolver $roleResolver,
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function execute(
        User $actor,
        Announcement $announcement,
        array $changes
    ): Announcement {
        return DB::transaction(function () use (
            $actor,
            $announcement,
            $changes
        ): Announcement {
            $locked = Announcement::query()
                ->with('roles')
                ->lockForUpdate()
                ->findOrFail($announcement->id);

            if (
                $locked->status
                !== AnnouncementStatus::Draft
            ) {
                throw ValidationException::withMessages([
                    'announcement' =>
                        'Hanya pengumuman DRAFT yang dapat diperbarui.',
                ]);
            }

            $old = [
                'title' =>
                    $locked->title,
                'content' =>
                    $locked->content,
                'expired_at' =>
                    $locked->expired_at
                        ?->toIso8601String(),
                'target_roles' =>
                    $locked->roles
                        ->pluck('name')
                        ->values()
                        ->all(),
            ];

            $fillable = [];

            foreach (
                [
                    'title',
                    'content',
                ]
                as $field
            ) {
                if (array_key_exists($field, $changes)) {
                    $fillable[$field] =
                        $changes[$field];
                }
            }

            if (
                array_key_exists(
                    'expired_at',
                    $changes
                )
            ) {
                $fillable['expired_at'] =
                    $changes['expired_at'] !== null
                        ? CarbonImmutable::parse(
                            (string)
                            $changes['expired_at'],
                            config('app.timezone')
                        )
                        : null;
            }

            if ($fillable !== []) {
                $locked->forceFill(
                    $fillable
                )->save();
            }

            if (
                array_key_exists(
                    'target_roles',
                    $changes
                )
            ) {
                $roles = $this->roleResolver
                    ->resolve(
                        $changes['target_roles']
                    );

                $locked->roles()->sync(
                    $roles->modelKeys()
                );
            }

            $locked->load('roles');

            $new = [
                'title' =>
                    $locked->title,
                'content' =>
                    $locked->content,
                'expired_at' =>
                    $locked->expired_at
                        ?->toIso8601String(),
                'target_roles' =>
                    $locked->roles
                        ->pluck('name')
                        ->values()
                        ->all(),
            ];

            $this->auditLogger->log(
                actor: $actor,
                module: 'announcement',
                action: 'UPDATE',
                entity: $locked,
                oldValues: $old,
                newValues: $new
            );

            return $locked->fresh([
                'creator',
                'schoolClass',
                'roles',
            ]);
        }, 3);
    }
}

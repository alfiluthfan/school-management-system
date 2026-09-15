<?php

namespace App\Actions\Announcements;

use App\Enums\Communication\AnnouncementStatus;
use App\Models\Auth\User;
use App\Models\Communication\Announcement;
use App\Services\System\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ArchiveAnnouncementAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function execute(
        User $actor,
        Announcement $announcement
    ): Announcement {
        return DB::transaction(function () use (
            $actor,
            $announcement
        ): Announcement {
            $locked = Announcement::query()
                ->lockForUpdate()
                ->findOrFail($announcement->id);

            if (
                $locked->status
                === AnnouncementStatus::Archived
            ) {
                throw ValidationException::withMessages([
                    'announcement' =>
                        'Pengumuman sudah diarsipkan.',
                ]);
            }

            $oldStatus =
                $locked->status->value;

            $locked->forceFill([
                'status' =>
                    AnnouncementStatus::Archived,
            ])->save();

            $this->auditLogger->log(
                actor: $actor,
                module: 'announcement',
                action: 'ARCHIVE',
                entity: $locked,
                oldValues: [
                    'status' => $oldStatus,
                ],
                newValues: [
                    'status' =>
                        AnnouncementStatus::Archived->value,
                ]
            );

            return $locked->fresh([
                'creator',
                'schoolClass',
                'roles',
            ]);
        }, 3);
    }
}

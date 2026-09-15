<?php

namespace App\Actions\Announcements;

use App\Enums\Communication\AnnouncementStatus;
use App\Models\Auth\User;
use App\Models\Communication\Announcement;
use App\Services\System\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublishAnnouncementAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function execute(
        User $actor,
        Announcement $announcement,
        ?CarbonImmutable $publishAt = null,
        bool $expiredAtWasProvided = false,
        ?CarbonImmutable $expiredAt = null
    ): Announcement {
        $publishAt ??= CarbonImmutable::now(
            config('app.timezone')
        );

        return DB::transaction(function () use (
            $actor,
            $announcement,
            $publishAt,
            $expiredAtWasProvided,
            $expiredAt
        ): Announcement {
            $locked = Announcement::query()
                ->lockForUpdate()
                ->findOrFail($announcement->id);

            if (
                $locked->status
                !== AnnouncementStatus::Draft
            ) {
                throw ValidationException::withMessages([
                    'announcement' =>
                        'Hanya pengumuman DRAFT yang dapat dipublikasikan.',
                ]);
            }

            $finalExpiredAt =
                $expiredAtWasProvided
                    ? $expiredAt
                    : (
                        $locked->expired_at
                            ? CarbonImmutable::instance(
                                $locked->expired_at
                            )
                            : null
                    );

            if (
                $finalExpiredAt
                && $finalExpiredAt->lte(
                    $publishAt
                )
            ) {
                throw ValidationException::withMessages([
                    'expired_at' =>
                        'expired_at harus setelah publish_at.',
                ]);
            }

            $old = [
                'status' =>
                    $locked->status->value,
                'publish_at' =>
                    $locked->publish_at
                        ?->toIso8601String(),
                'expired_at' =>
                    $locked->expired_at
                        ?->toIso8601String(),
            ];

            $locked->forceFill([
                'status' =>
                    AnnouncementStatus::Published,
                'publish_at' =>
                    $publishAt,
                'expired_at' =>
                    $finalExpiredAt,
            ])->save();

            $this->auditLogger->log(
                actor: $actor,
                module: 'announcement',
                action: 'PUBLISH',
                entity: $locked,
                oldValues: $old,
                newValues: [
                    'status' =>
                        AnnouncementStatus::Published->value,
                    'publish_at' =>
                        $locked->publish_at
                            ?->toIso8601String(),
                    'expired_at' =>
                        $locked->expired_at
                            ?->toIso8601String(),
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

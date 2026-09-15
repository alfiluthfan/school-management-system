<?php

namespace App\Actions\Approvals;

use App\Enums\System\ApprovalAction;
use App\Enums\System\ApprovalModule;
use App\Enums\System\ApprovalStatus;
use App\Models\Auth\User;
use App\Models\System\Approval;
use App\Services\Approvals\ApprovalHandlerRegistry;
use App\Services\System\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitApprovalRequestAction
{
    public function __construct(
        private readonly ApprovalHandlerRegistry $registry,
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(
        User $requester,
        ApprovalModule $module,
        ApprovalAction $action,
        Model $entity,
        string $reason,
        array $payload = []
    ): Approval {
        $handler = $this->registry->handler(
            $module,
            $action
        );

        $handler->validateSubmission(
            $requester,
            $entity,
            $payload
        );

        $pendingKey = $handler->pendingKey($entity);

        return DB::transaction(function () use (
            $requester,
            $module,
            $action,
            $entity,
            $reason,
            $payload,
            $pendingKey
        ): Approval {
            $approval = Approval::query()->firstOrCreate(
                [
                    'pending_key' => $pendingKey,
                ],
                [
                    'requested_by' => $requester->id,
                    'reviewed_by' => null,
                    'module' => $module,
                    'entity_type' =>
                        $entity->getMorphClass(),
                    'entity_id' => $entity->getKey(),
                    'action' => $action,
                    'reason' => $reason,
                    'request_payload' =>
                        $payload ?: null,
                    'status' =>
                        ApprovalStatus::Pending,
                    'review_notes' => null,
                    'reviewed_at' => null,
                ]
            );

            if (! $approval->wasRecentlyCreated) {
                throw ValidationException::withMessages([
                    'approval' =>
                        'Masih ada approval PENDING untuk operasi yang sama.',
                ]);
            }

            $this->auditLogger->log(
                actor: $requester,
                module: 'approval',
                action: 'SUBMIT',
                entity: $approval,
                oldValues: null,
                newValues: [
                    'module' => $module->value,
                    'action' => $action->value,
                    'status' =>
                        ApprovalStatus::Pending->value,
                ],
                metadata: [
                    'target_type' =>
                        $entity->getMorphClass(),
                    'target_id' => $entity->getKey(),
                    'target_uuid' =>
                        $entity->uuid ?? null,
                ]
            );

            return $approval;
        }, 3);
    }
}

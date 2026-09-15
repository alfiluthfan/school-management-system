<?php

namespace App\Services\Approvals;

use App\Contracts\Approvals\ApprovalHandler;
use App\Enums\System\ApprovalAction;
use App\Enums\System\ApprovalModule;
use App\Services\Approvals\Handlers\SavingsReversalApprovalHandler;
use DomainException;

final class ApprovalHandlerRegistry
{
    public function __construct(
        private readonly SavingsReversalApprovalHandler $savingsReversal
    ) {
    }

    public function handler(
        ApprovalModule $module,
        ApprovalAction $action
    ): ApprovalHandler {
        return match ([$module, $action]) {
            [
                ApprovalModule::Saving,
                ApprovalAction::Void,
            ] => $this->savingsReversal,

            default => throw new DomainException(
                sprintf(
                    'Approval handler belum tersedia untuk %s / %s.',
                    $module->value,
                    $action->value
                )
            ),
        };
    }
}

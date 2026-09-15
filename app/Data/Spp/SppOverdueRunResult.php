<?php

namespace App\Data\Spp;

final readonly class SppOverdueRunResult
{
    public function __construct(
        public int $scanned,
        public int $markedOverdue,
        public int $remindersCreated,
        public int $deliveryJobsDispatched,
        public int $withoutRecipients,
        public int $skippedByCadence,
    ) {
    }
}

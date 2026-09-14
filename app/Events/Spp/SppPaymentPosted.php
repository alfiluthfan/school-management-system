<?php

namespace App\Events\Spp;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SppPaymentPosted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $sppPaymentId
    ) {
    }
}

<?php

namespace App\Data\WhatsApp;

final readonly class WhatsAppSendResult
{
    public function __construct(
        public string $providerMessageId
    ) {
    }
}

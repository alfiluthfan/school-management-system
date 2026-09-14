<?php

namespace App\Contracts\WhatsApp;

use App\Data\WhatsApp\WhatsAppSendResult;

interface WhatsAppProvider
{
    public function sendText(
        string $recipient,
        string $message
    ): WhatsAppSendResult;
}

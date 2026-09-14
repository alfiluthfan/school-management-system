<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProvider;
use App\Data\WhatsApp\WhatsAppSendResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class LogWhatsAppProvider implements WhatsAppProvider
{
    public function sendText(
        string $recipient,
        string $message
    ): WhatsAppSendResult {
        Log::info('WhatsApp notification (log driver)', [
            'recipient' => $recipient,
            'message' => $message,
        ]);

        return new WhatsAppSendResult(
            providerMessageId: 'log-'.Str::uuid()
        );
    }
}

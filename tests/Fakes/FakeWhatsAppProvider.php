<?php

namespace Tests\Fakes;

use App\Contracts\WhatsApp\WhatsAppProvider;
use App\Data\WhatsApp\WhatsAppSendResult;
use RuntimeException;

class FakeWhatsAppProvider implements WhatsAppProvider
{
    public int $failuresRemaining = 0;

    /**
     * @var list<array{recipient: string, message: string}>
     */
    public array $sent = [];

    public function sendText(
        string $recipient,
        string $message
    ): WhatsAppSendResult {
        if ($this->failuresRemaining > 0) {
            $this->failuresRemaining--;

            throw new RuntimeException(
                'Fake WhatsApp provider failure.'
            );
        }

        $this->sent[] = [
            'recipient' => $recipient,
            'message' => $message,
        ];

        return new WhatsAppSendResult(
            providerMessageId: 'fake-message-id'
        );
    }
}

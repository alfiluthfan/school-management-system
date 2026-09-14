<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProvider;
use App\Data\WhatsApp\WhatsAppSendResult;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;

final class MetaWhatsAppCloudProvider implements WhatsAppProvider
{
    public function sendText(
        string $recipient,
        string $message
    ): WhatsAppSendResult {
        $config = config(
            'school-notifications.whatsapp.meta'
        );

        $baseUrl = rtrim(
            (string) ($config['base_url'] ?? ''),
            '/'
        );
        $version = trim(
            (string) ($config['version'] ?? ''),
            '/'
        );
        $phoneNumberId = (string) (
            $config['phone_number_id'] ?? ''
        );
        $accessToken = (string) (
            $config['access_token'] ?? ''
        );
        $timeout = (int) (
            $config['timeout_seconds'] ?? 15
        );

        if (
            $baseUrl === ''
            || $version === ''
            || $phoneNumberId === ''
            || $accessToken === ''
        ) {
            throw new LogicException(
                'Konfigurasi WhatsApp Cloud API belum lengkap.'
            );
        }

        $url = sprintf(
            '%s/%s/%s/messages',
            $baseUrl,
            $version,
            $phoneNumberId
        );

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipient,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ])
            ->throw();

        $providerMessageId = $response->json(
            'messages.0.id'
        );

        if (! is_string($providerMessageId)
            || $providerMessageId === '') {
            throw new RuntimeException(
                'Provider WhatsApp tidak mengembalikan message ID.'
            );
        }

        return new WhatsAppSendResult(
            providerMessageId: $providerMessageId
        );
    }
}

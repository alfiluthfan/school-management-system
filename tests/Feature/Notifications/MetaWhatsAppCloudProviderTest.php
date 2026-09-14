<?php

namespace Tests\Feature\Notifications;

use App\Services\WhatsApp\MetaWhatsAppCloudProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class MetaWhatsAppCloudProviderTest extends NotificationTestCase
{
    public function test_meta_provider_builds_expected_request_and_returns_id(): void
    {
        config([
            'school-notifications.whatsapp.meta' => [
                'base_url' =>
                    'https://graph.example.test',
                'version' => 'v99.0',
                'phone_number_id' => 'phone-123',
                'access_token' => 'secret-test-token',
                'timeout_seconds' => 15,
            ],
        ]);

        Http::fake([
            '*' => Http::response([
                'messages' => [
                    [
                        'id' => 'wamid.test.123',
                    ],
                ],
            ], 200),
        ]);

        $result = app(
            MetaWhatsAppCloudProvider::class
        )->sendText(
            '6281234567890',
            'Test message'
        );

        $this->assertSame(
            'wamid.test.123',
            $result->providerMessageId
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->url()
                    === 'https://graph.example.test/'
                        .'v99.0/phone-123/messages'
                    && $request->hasHeader(
                        'Authorization',
                        'Bearer secret-test-token'
                    )
                    && $request['messaging_product']
                        === 'whatsapp'
                    && $request['to']
                        === '6281234567890'
                    && $request['type']
                        === 'text'
                    && $request['text']['body']
                        === 'Test message';
            }
        );
    }
}

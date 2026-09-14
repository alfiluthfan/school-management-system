<?php

namespace App\Providers;

use App\Contracts\WhatsApp\WhatsAppProvider;
use App\Services\WhatsApp\LogWhatsAppProvider;
use App\Services\WhatsApp\MetaWhatsAppCloudProvider;
use Illuminate\Support\ServiceProvider;
use LogicException;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            WhatsAppProvider::class,
            function ($app): WhatsAppProvider {
                $driver = config(
                    'school-notifications.whatsapp.driver',
                    'log'
                );

                return match ($driver) {
                    'log' => $app->make(
                        LogWhatsAppProvider::class
                    ),
                    'meta' => $app->make(
                        MetaWhatsAppCloudProvider::class
                    ),
                    default => throw new LogicException(
                        'WhatsApp driver tidak dikenali: '
                        .$driver
                    ),
                };
            }
        );
    }
}

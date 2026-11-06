<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Tests\TestCase;

/**
 * La API y la PWA viven en subdominios distintos: los enlaces de los correos
 * deben apuntar a la PWA (FRONTEND_URL), no al host de la API.
 */
class NotificationMailTest extends TestCase
{
    public function test_notification_email_links_to_the_frontend(): void
    {
        config(['app.frontend_url' => 'https://app.test']);

        $html = view('emails.notification', [
            'title' => 'Servicio próximo',
            'body' => 'Tu Corolla cumple 20.000 km.',
            'url' => '/recordatorios',
        ])->render();

        $this->assertStringContainsString('https://app.test/recordatorios', $html);
    }

    public function test_welcome_email_links_to_the_frontend(): void
    {
        config(['app.frontend_url' => 'https://app.test']);

        $html = view('emails.welcome', [
            'user' => new User(['name' => 'Ana']),
        ])->render();

        $this->assertStringContainsString('href="https://app.test"', $html);
    }
}

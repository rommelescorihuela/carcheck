<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Services\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\WebPushMessage;
use Tests\TestCase;

class WebPushSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_vapid_keys_it_sends_nothing_and_does_not_fail(): void
    {
        config([
            'webpush.vapid.public_key' => null,
            'webpush.vapid.private_key' => null,
        ]);

        $user = User::factory()->create();
        $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc123', 'clave', 'token');

        $delivered = app(WebPushSender::class)->send(
            $user,
            (new WebPushMessage)->title('Aviso')->body('Cuerpo'),
        );

        $this->assertSame(0, $delivered);
        // La suscripción no se poda: el problema es de configuración, no del dispositivo.
        $this->assertSame(1, $user->pushSubscriptions()->count());
    }

    public function test_user_without_subscriptions_sends_nothing(): void
    {
        config([
            'webpush.vapid.public_key' => 'clave-publica',
            'webpush.vapid.private_key' => 'clave-privada',
        ]);

        $delivered = app(WebPushSender::class)->send(
            User::factory()->create(),
            (new WebPushMessage)->title('Aviso')->body('Cuerpo'),
        );

        $this->assertSame(0, $delivered);
    }
}

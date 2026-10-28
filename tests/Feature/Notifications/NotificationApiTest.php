<?php

namespace Tests\Feature\Notifications;

use App\Contracts\PushSender;
use App\Enums\NotificationCategory;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakePushSender;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private FakePushSender $push;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->push = new FakePushSender;
        $this->app->instance(PushSender::class, $this->push);
    }

    public function test_inbox_only_returns_own_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->notify($user, 'Propia');
        $this->notify($other, 'Ajena');

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Propia')
            ->assertJsonPath('data.0.category', 'system')
            ->assertJsonPath('data.0.category_label', 'Sistema')
            ->assertJsonPath('data.0.read_at', null);
    }

    public function test_inbox_is_ordered_newest_first(): void
    {
        $user = User::factory()->create();
        $old = $this->notify($user, 'Antigua');
        $old->forceFill(['created_at' => now()->subDay()])->save();
        $this->notify($user, 'Reciente');

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Reciente')
            ->assertJsonPath('data.1.title', 'Antigua');
    }

    public function test_unread_count_and_marking_one_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->notify($user, 'Aviso');

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 1);

        $this->postJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.title', 'Aviso');

        $this->assertNotNull($notification->fresh()->read_at);

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 0);
    }

    public function test_mark_all_as_read(): void
    {
        $user = User::factory()->create();
        $this->notify($user, 'Uno');
        $this->notify($user, 'Dos');

        Sanctum::actingAs($user);

        $this->postJson('/api/notifications/read-all')->assertOk();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_user_can_delete_a_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->notify($user, 'Aviso');

        Sanctum::actingAs($user);

        $this->deleteJson("/api/notifications/{$notification->id}")->assertOk();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_foreign_notification_is_not_found(): void
    {
        $user = User::factory()->create();
        $notification = $this->notify(User::factory()->create(), 'Ajena');

        Sanctum::actingAs($user);

        $this->postJson("/api/notifications/{$notification->id}/read")->assertNotFound();
        $this->deleteJson("/api/notifications/{$notification->id}")->assertNotFound();
        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }

    public function test_preferences_endpoint_returns_defaults_and_push_state(): void
    {
        config([
            'webpush.vapid.public_key' => 'clave-publica',
            'webpush.vapid.private_key' => 'clave-privada',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/notifications/preferences')
            ->assertOk()
            ->assertJsonCount(5, 'data.preferences')
            ->assertJsonPath('data.preferences.0.category', 'system')
            ->assertJsonPath('data.preferences.0.in_app', true)
            ->assertJsonPath('data.push_configured', true);
    }

    public function test_user_can_update_preferences(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->putJson('/api/notifications/preferences', [
            'preferences' => [
                ['category' => 'billing', 'in_app' => true, 'email' => false, 'push' => false],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.preferences.1.category', 'billing')
            ->assertJsonPath('data.preferences.1.email', false)
            ->assertJsonPath('data.preferences.1.push', false);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'category' => 'billing',
            'email' => false,
            'push' => false,
        ]);
    }

    public function test_preferences_require_known_categories_and_booleans(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/notifications/preferences', [
            'preferences' => [
                ['category' => 'inventada', 'in_app' => 'sí', 'email' => true, 'push' => true],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preferences.0.category', 'preferences.0.in_app']);
    }

    public function test_public_key_is_null_without_vapid(): void
    {
        config([
            'webpush.vapid.public_key' => null,
            'webpush.vapid.private_key' => null,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/notifications/push/public-key')
            ->assertOk()
            ->assertJsonPath('data.public_key', null);
    }

    public function test_public_key_is_returned_when_configured(): void
    {
        config([
            'webpush.vapid.public_key' => 'clave-publica',
            'webpush.vapid.private_key' => 'clave-privada',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/notifications/push/public-key')
            ->assertOk()
            ->assertJsonPath('data.public_key', 'clave-publica');
    }

    public function test_user_can_register_and_remove_a_push_subscription(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/notifications/push-subscriptions', $this->subscriptionPayload())
            ->assertCreated()
            ->assertJsonPath('data.endpoint', 'https://fcm.googleapis.com/fcm/send/abc123')
            ->assertJsonPath('data.content_encoding', 'aes128gcm');

        $this->assertSame(1, $user->pushSubscriptions()->count());

        $this->deleteJson('/api/notifications/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ])->assertOk();

        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_push_subscription_moves_to_the_current_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        Sanctum::actingAs($first);
        $this->postJson('/api/notifications/push-subscriptions', $this->subscriptionPayload())->assertCreated();

        Sanctum::actingAs($second);
        $this->postJson('/api/notifications/push-subscriptions', $this->subscriptionPayload())->assertCreated();

        $this->assertSame(0, $first->pushSubscriptions()->count());
        $this->assertSame(1, $second->pushSubscriptions()->count());
    }

    public function test_push_subscription_requires_valid_keys(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/notifications/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['auth' => 'x'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('keys.p256dh');
    }

    public function test_test_endpoint_sends_a_notification_to_the_current_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/notifications/test')
            ->assertOk()
            ->assertJsonPath('data.title', 'Notificación de prueba')
            ->assertJsonPath('data.category', 'system')
            ->assertJsonPath('data.url', '/notificaciones');

        $this->assertSame(1, $user->notifications()->count());
        $this->assertCount(1, $this->push->sent);
    }

    private function notify(User $user, string $title): DatabaseNotification
    {
        return app(Notifier::class)->send(
            $user,
            NotificationCategory::System,
            $title,
            'Cuerpo del aviso.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionPayload(): array
    {
        return [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => [
                'p256dh' => 'BJ5falso-pero-con-forma-de-clave-publica',
                'auth' => 'auth-secreto',
            ],
            'content_encoding' => 'aes128gcm',
        ];
    }
}

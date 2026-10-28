<?php

namespace Tests\Feature\Notifications;

use App\Contracts\PushSender;
use App\Enums\NotificationCategory;
use App\Mail\NotificationMail;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakePushSender;
use Tests\TestCase;

class NotifierTest extends TestCase
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

    public function test_sends_in_app_email_and_push(): void
    {
        $user = User::factory()->create();
        $vehicleId = 42;

        $notification = app(Notifier::class)->send(
            $user,
            NotificationCategory::Maintenance,
            'Cambio de aceite',
            'Tu Corolla cumple 10.000 km.',
            ['vehicle_id' => $vehicleId],
            '/vehiculos/1/mantenimiento',
        );

        $this->assertNotNull($notification);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'notifiable_id' => $user->id,
            'read_at' => null,
        ]);

        $data = $notification->fresh()->data;
        $this->assertSame('maintenance', $data['category']);
        $this->assertSame('Mantenimiento', $data['category_label']);
        $this->assertSame($vehicleId, $data['payload']['vehicle_id']);
        $this->assertSame('/vehiculos/1/mantenimiento', $data['url']);

        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->title === 'Cambio de aceite'
                && $mail->body === 'Tu Corolla cumple 10.000 km.';
        });

        $this->assertCount(1, $this->push->sent);
        $this->assertTrue($this->push->sent[0]['user']->is($user));
        $this->assertSame('Cambio de aceite', $this->push->sent[0]['message']->toArray()['title']);
    }

    public function test_disabled_in_app_channel_skips_the_inbox_row(): void
    {
        $user = User::factory()->create();
        NotificationPreference::factory()->for($user)->forCategory(NotificationCategory::System)->create([
            'in_app' => false,
        ]);

        $notification = app(Notifier::class)->send(
            $user,
            NotificationCategory::System,
            'Aviso',
            'Cuerpo',
        );

        $this->assertNull($notification);
        $this->assertDatabaseCount('notifications', 0);
        // Los otros canales siguen activos.
        Mail::assertSent(NotificationMail::class);
        $this->assertCount(1, $this->push->sent);
    }

    public function test_disabled_email_and_push_channels_skip_them(): void
    {
        $user = User::factory()->create();
        NotificationPreference::factory()->for($user)->forCategory(NotificationCategory::Documents)->create([
            'email' => false,
            'push' => false,
        ]);

        $notification = app(Notifier::class)->send(
            $user,
            NotificationCategory::Documents,
            'Documento por vencer',
            'Tu seguro vence en 10 días.',
        );

        $this->assertNotNull($notification);
        Mail::assertNothingSent();
        $this->assertCount(0, $this->push->sent);
    }

    public function test_send_to_many_notifies_each_user(): void
    {
        $users = User::factory()->count(3)->create();

        $count = app(Notifier::class)->sendToMany(
            $users,
            NotificationCategory::Reminders,
            'Recordatorio',
            'Revisión próxima.',
        );

        $this->assertSame(3, $count);
        $this->assertDatabaseCount('notifications', 3);
        $this->assertCount(3, $this->push->sent);
    }
}

<?php

namespace Tests\Feature\Notifications;

use App\Contracts\PushSender;
use App\Jobs\SendPushNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\Support\FakePushSender;
use Tests\TestCase;

class SendPushNotificationTest extends TestCase
{
    public function test_the_job_sends_the_push_with_its_payload(): void
    {
        $push = new FakePushSender;
        $this->app->instance(PushSender::class, $push);

        $user = User::factory()->make();
        $job = new SendPushNotification($user, 'Cambio de aceite', 'Tu Corolla cumple 10.000 km.', [
            'category' => 'maintenance',
            'payload' => ['vehicle_id' => 7],
        ]);

        $this->assertInstanceOf(ShouldQueue::class, $job);

        $job->handle($push);

        $this->assertCount(1, $push->sent);
        $this->assertTrue($push->sent[0]['user']->is($user));

        $message = $push->sent[0]['message']->toArray();
        $this->assertSame('Cambio de aceite', $message['title']);
        $this->assertSame('Tu Corolla cumple 10.000 km.', $message['body']);
        $this->assertSame(['vehicle_id' => 7], $message['data']['payload']);
    }
}

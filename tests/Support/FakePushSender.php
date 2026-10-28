<?php

namespace Tests\Support;

use App\Contracts\PushSender;
use App\Models\User;
use NotificationChannels\WebPush\WebPushMessageInterface;

/**
 * Sustituto de `WebPushSender` que registra los envíos sin tocar la red.
 */
class FakePushSender implements PushSender
{
    /**
     * @var array<int, array{user: User, message: WebPushMessageInterface}>
     */
    public array $sent = [];

    public function send(User $user, WebPushMessageInterface $message): int
    {
        $this->sent[] = ['user' => $user, 'message' => $message];

        return 1;
    }
}

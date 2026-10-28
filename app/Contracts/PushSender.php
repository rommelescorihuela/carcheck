<?php

namespace App\Contracts;

use App\Models\User;
use NotificationChannels\WebPush\WebPushMessageInterface;

/**
 * Envío de push a los dispositivos suscritos de un usuario. La interfaz permite sustituir la
 * implementación real por un fake en tests sin tocar la red.
 */
interface PushSender
{
    /**
     * @return int número de dispositivos que aceptaron el mensaje
     */
    public function send(User $user, WebPushMessageInterface $message): int;
}

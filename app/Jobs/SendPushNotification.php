<?php

namespace App\Jobs;

use App\Contracts\PushSender;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Envío push en segundo plano: el navegador del usuario puede tardar y no debe retrasar la
 * petición que originó el aviso.
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public bool $deleteWhenMissingModels = true;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public User $user,
        public string $title,
        public string $body,
        public array $payload = [],
    ) {}

    public function handle(PushSender $push): void
    {
        $push->send($this->user, (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon('/icons/icon-192.png')
            ->data($this->payload));
    }
}

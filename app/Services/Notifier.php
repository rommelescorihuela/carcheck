<?php

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Jobs\SendPushNotification;
use App\Mail\NotificationMail;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Puerta de entrada única para emitir avisos. Guarda siempre el aviso in-app (si el usuario lo
 * permite) y luego intenta email y push. Un fallo en un canal externo no revierte la bandeja.
 */
class Notifier
{
    public function __construct(
        private readonly NotificationPreferences $preferences,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function send(
        User $user,
        NotificationCategory $category,
        string $title,
        string $body,
        array $data = [],
        ?string $url = null,
    ): ?DatabaseNotification {
        $payload = [
            'category' => $category->value,
            'category_label' => $category->label(),
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'payload' => $data,
        ];

        $notification = null;

        if ($this->preferences->allows($user, $category, 'in_app')) {
            $notification = $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'carcheck.notification',
                'data' => $payload,
                'read_at' => null,
            ]);
        }

        if ($this->preferences->allows($user, $category, 'email') && filled($user->email)) {
            try {
                // La bandeja ya quedó guardada: el correo puede salir en segundo plano.
                Mail::to($user)->queue(new NotificationMail($title, $body, $url));
            } catch (Throwable $exception) {
                Log::warning('No se pudo encolar el email de aviso.', [
                    'user_id' => $user->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($this->preferences->allows($user, $category, 'push')) {
            try {
                SendPushNotification::dispatch($user, $title, $body, $payload);
            } catch (Throwable $exception) {
                Log::warning('No se pudo encolar el push de aviso.', [
                    'user_id' => $user->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $notification;
    }

    /**
     * @param  iterable<User>  $users
     * @param  array<string, mixed>  $data
     * @return int número de usuarios a los que se intentó avisar
     */
    public function sendToMany(
        iterable $users,
        NotificationCategory $category,
        string $title,
        string $body,
        array $data = [],
        ?string $url = null,
    ): int {
        $count = 0;

        foreach ($users as $user) {
            $this->send($user, $category, $title, $body, $data, $url);
            $count++;
        }

        return $count;
    }
}

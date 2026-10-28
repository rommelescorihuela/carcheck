<?php

namespace App\Services;

use App\Contracts\PushSender;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\WebPushMessageInterface;
use Throwable;

/**
 * Envío push real con VAPID. Sin claves configuradas no es un error: el aviso ya quedó en la
 * bandeja y el push simplemente se omite.
 */
class WebPushSender implements PushSender
{
    public function send(User $user, WebPushMessageInterface $message): int
    {
        $publicKey = config('webpush.vapid.public_key');
        $privateKey = config('webpush.vapid.private_key');

        if (blank($publicKey) || blank($privateKey)) {
            Log::info('Push omitido: faltan las claves VAPID.');

            return 0;
        }

        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('webpush.vapid.subject') ?: url('/'),
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);

            $payload = json_encode($message->toArray(), JSON_THROW_ON_ERROR);

            foreach ($subscriptions as $subscription) {
                if (blank($subscription->public_key) || blank($subscription->auth_token)) {
                    continue;
                }

                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'keys' => [
                            'p256dh' => $subscription->public_key,
                            'auth' => $subscription->auth_token,
                        ],
                        'contentEncoding' => $subscription->content_encoding ?? ContentEncoding::aes128gcm,
                    ]),
                    $payload,
                    $message->getOptions(),
                );
            }

            $delivered = 0;

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $delivered++;

                    continue;
                }

                // 404/410: el navegador ya no existe; se descarta para no reintentar siempre.
                if ($report->isSubscriptionExpired()) {
                    $user->pushSubscriptions()
                        ->where('endpoint', $report->getEndpoint())
                        ->delete();
                }

                Log::warning('Push fallido.', [
                    'endpoint' => $report->getEndpoint(),
                    'reason' => $report->getReason(),
                ]);
            }

            return $delivered;
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar el push.', [
                'user_id' => $user->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return 0;
        }
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/')
            .'/restablecer/'.$this->token
            .'?email='.urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Restablece tu contraseña de CarCheck')
            ->greeting('Hola '.$notifiable->name)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $url)
            ->line('El enlace vence en 60 minutos y solo puede usarse una vez.')
            ->line('Si no solicitaste el cambio, ignora este correo: tu contraseña sigue igual.');
    }
}

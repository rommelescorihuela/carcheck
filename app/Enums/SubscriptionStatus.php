<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Prueba gratuita',
            self::PendingPayment => 'Pendiente de pago',
            self::Active => 'Activa',
            self::Canceled => 'Cancelada',
        };
    }
}

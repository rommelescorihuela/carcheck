<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Cualquier miembro del propietario de la suscripción puede ver sus pagos.
     */
    public function view(User $user, Payment $payment): bool
    {
        $owner = $payment->subscription->owner;

        return $owner instanceof User
            ? $owner->is($user)
            : $owner->hasMember($user);
    }

    /**
     * Reportar/retirar comprobantes: propietario personal u `owner`/`admin` de la organización.
     */
    public function update(User $user, Payment $payment): bool
    {
        $owner = $payment->subscription->owner;

        if ($owner instanceof User) {
            return $owner->is($user);
        }

        return in_array($owner->roleOf($user), ['owner', 'admin'], true);
    }

    /**
     * Aprobar o rechazar pagos es exclusivo del personal de plataforma.
     */
    public function review(User $user, Payment $payment): bool
    {
        return $user->hasRole('super_admin');
    }
}

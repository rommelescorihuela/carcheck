<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de facturación: estado efectivo de la suscripción, prueba gratuita, activación por pago
 * manual y extensión del periodo. Es la única fuente de verdad: la API y el panel Filament llaman
 * aquí, no reimplementan nada.
 */
class Billing
{
    /**
     * Suscripción más reciente del propietario (la "actual").
     */
    public function currentFor(User|Organization $owner): ?Subscription
    {
        return Subscription::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->with('plan')
            ->latest('id')
            ->first();
    }

    /**
     * Estado real: el paso del tiempo convierte una prueba o un periodo en `expired` sin tocar la BD.
     */
    public function effectiveStatus(Subscription $subscription): string
    {
        return match ($subscription->status) {
            SubscriptionStatus::Trialing => $this->trialExpired($subscription) ? 'expired' : 'trialing',
            SubscriptionStatus::Active => $this->periodExpired($subscription) ? 'expired' : 'active',
            default => $subscription->status->value,
        };
    }

    /**
     * Una suscripción cancelada conserva el acceso hasta el fin del periodo ya pagado.
     */
    public function hasAccess(?Subscription $subscription): bool
    {
        if ($subscription === null) {
            return false;
        }

        if (in_array($this->effectiveStatus($subscription), ['trialing', 'active'], true)) {
            return true;
        }

        return $subscription->status === SubscriptionStatus::Canceled
            && $subscription->current_period_end !== null
            && $subscription->current_period_end->gte(today());
    }

    public function trialDaysLeft(Subscription $subscription): ?int
    {
        if ($subscription->status !== SubscriptionStatus::Trialing || $subscription->trial_ends_at === null) {
            return null;
        }

        return max(0, (int) today()->diffInDays($subscription->trial_ends_at->copy()->startOfDay(), false));
    }

    public function daysLeft(Subscription $subscription): ?int
    {
        if ($subscription->status === SubscriptionStatus::Trialing) {
            return $this->trialDaysLeft($subscription);
        }

        if ($subscription->current_period_end === null) {
            return null;
        }

        return (int) today()->diffInDays($subscription->current_period_end->copy()->startOfDay(), false);
    }

    /**
     * Uso actual frente a los límites del plan.
     *
     * @return array{vehicles: int, members: int|null}
     */
    public function usage(User|Organization $owner): array
    {
        $vehicles = Vehicle::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->count();

        return [
            'vehicles' => $vehicles,
            'members' => $owner instanceof Organization ? $owner->members()->count() : null,
        ];
    }

    /**
     * Plan que gobierna el uso del propietario: el de su suscripción actual o, si todavía no
     * eligió ninguno, el más barato de los activos. Así una cuenta recién creada no queda sin
     * límites solo por no haber pasado aún por el alta de plan.
     */
    public function planFor(User|Organization $owner): ?Plan
    {
        return $this->currentFor($owner)?->plan ?? $this->defaultPlan();
    }

    public function defaultPlan(): ?Plan
    {
        return Plan::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->first();
    }

    /**
     * Bloquea crear vehículos por encima del plan. Se llama desde la API (alta y traspaso).
     */
    public function assertCanAddVehicle(User|Organization $owner): void
    {
        $this->assertAccess($owner);

        $limit = $this->planFor($owner)?->max_vehicles;

        if ($limit === null) {
            return;
        }

        if ($this->usage($owner)['vehicles'] >= $limit) {
            $this->failLimit(
                'plan',
                $limit === 1
                    ? 'Tu plan permite 1 vehículo. Mejora el plan para añadir otro.'
                    : "Tu plan permite {$limit} vehículos. Mejora el plan para añadir otro.",
            );
        }
    }

    /**
     * Bloquea invitar o aceptar miembros por encima del plan. `$alreadyPending` son las
     * invitaciones vigentes que todavía no son miembros (también ocupan sitio).
     */
    public function assertCanAddMember(Organization $organization, int $alreadyPending = 0): void
    {
        $this->assertAccess($organization);

        $limit = $this->planFor($organization)?->max_members;

        if ($limit === null) {
            return;
        }

        $used = $organization->members()->count() + max(0, $alreadyPending);

        if ($used >= $limit) {
            $this->failLimit(
                'email',
                $limit === 1
                    ? 'Tu plan permite 1 miembro. Mejora el plan para invitar a alguien más.'
                    : "Tu plan permite {$limit} miembros. Mejora el plan para invitar a alguien más.",
            );
        }
    }

    /**
     * Un propietario con suscripción vencida o pendiente de pago conserva la lectura, pero no
     * puede seguir dando de alta recursos hasta regularizarla.
     */
    private function assertAccess(User|Organization $owner): void
    {
        $subscription = $this->currentFor($owner);

        if ($subscription !== null && ! $this->hasAccess($subscription)) {
            $this->failLimit(
                'subscription',
                'Tu suscripción no está activa. Elige un plan y reporta el pago para seguir creando.',
            );
        }
    }

    /**
     * @param  array<string, list<string>>  $messages
     */
    private function failLimit(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => [$message]]);
    }

    public function subscribe(User|Organization $owner, Plan $plan): Subscription
    {
        $this->assertPlanAvailable($plan);

        $current = $this->currentFor($owner);

        if ($current !== null && $this->hasAccess($current)) {
            throw ValidationException::withMessages([
                'plan_id' => 'Ya tienes una suscripción vigente.',
            ]);
        }

        // La prueba gratuita es única por propietario: se mira su historial completo.
        $trialUsed = Subscription::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->whereNotNull('trial_ends_at')
            ->exists();

        $withTrial = $plan->trial_days > 0 && ! $trialUsed;

        $subscription = new Subscription([
            'plan_id' => $plan->getKey(),
            'status' => $withTrial ? SubscriptionStatus::Trialing : SubscriptionStatus::PendingPayment,
            'trial_ends_at' => $withTrial ? now()->addDays($plan->trial_days) : null,
        ]);

        $subscription->owner()->associate($owner);
        $subscription->save();

        return $subscription->load('plan');
    }

    public function changePlan(Subscription $subscription, Plan $plan): Subscription
    {
        $this->assertPlanAvailable($plan);

        $subscription->update(['plan_id' => $plan->getKey()]);

        return $subscription->load('plan');
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now(),
        ]);

        return $subscription->load('plan');
    }

    /**
     * Aprueba un pago y activa/extiende la suscripción. Si el periodo pagado sigue en el futuro, el
     * nuevo periodo se encadena a su fin (renovación); si no, empieza en la fecha del pago.
     */
    public function approvePayment(Payment $payment, User $reviewer): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            throw ValidationException::withMessages([
                'payment' => 'Solo se pueden aprobar pagos pendientes.',
            ]);
        }

        $subscription = $payment->subscription;
        $plan = $subscription->plan;

        if ($payment->amount_base + 0.001 < (float) $plan->price) {
            throw ValidationException::withMessages([
                'amount' => 'El pago no cubre el precio del plan.',
            ]);
        }

        $start = $subscription->current_period_end !== null
            && $subscription->current_period_end->isFuture()
                ? $subscription->current_period_end->copy()->startOfDay()
                : Carbon::parse($payment->paid_on)->startOfDay();

        $end = $plan->interval->periodEnd($start);

        DB::transaction(function () use ($payment, $reviewer, $subscription, $start, $end): void {
            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $start->toDateString(),
                'current_period_end' => $end->toDateString(),
                'payment_method' => $payment->method,
            ]);

            $payment->update([
                'status' => PaymentStatus::Approved,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->getKey(),
            ]);
        });

        return $payment->fresh(['subscription.plan']);
    }

    public function rejectPayment(Payment $payment, User $reviewer, ?string $reason = null): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            throw ValidationException::withMessages([
                'payment' => 'Solo se pueden rechazar pagos pendientes.',
            ]);
        }

        $notes = $payment->notes;

        if (filled($reason)) {
            $notes = trim(($notes === null ? '' : $notes."\n").'Motivo del rechazo: '.$reason);
        }

        $payment->update([
            'status' => PaymentStatus::Rejected,
            'notes' => $notes,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->getKey(),
        ]);

        return $payment->fresh(['subscription.plan']);
    }

    private function assertPlanAvailable(Plan $plan): void
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages([
                'plan_id' => 'El plan no está disponible.',
            ]);
        }
    }

    private function trialExpired(Subscription $subscription): bool
    {
        return $subscription->trial_ends_at !== null
            && $subscription->trial_ends_at->lt(now());
    }

    private function periodExpired(Subscription $subscription): bool
    {
        return $subscription->current_period_end !== null
            && $subscription->current_period_end->lt(today());
    }
}

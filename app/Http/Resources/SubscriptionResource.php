<?php

namespace App\Http\Resources;

use App\Services\Billing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $billing = app(Billing::class);
        $effective = $billing->effectiveStatus($this->resource);

        return [
            'id' => $this->id,
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'effective_status' => $effective,
            'effective_status_label' => match ($effective) {
                'trialing' => 'Prueba gratuita',
                'pending_payment' => 'Pendiente de pago',
                'active' => 'Activa',
                'canceled' => 'Cancelada',
                default => 'Vencida',
            },
            'has_access' => $billing->hasAccess($this->resource),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'trial_days_left' => $billing->trialDaysLeft($this->resource),
            'current_period_start' => $this->current_period_start?->toDateString(),
            'current_period_end' => $this->current_period_end?->toDateString(),
            'days_left' => $billing->daysLeft($this->resource),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->label(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

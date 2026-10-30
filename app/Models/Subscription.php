<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Services\Billing;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'plan_id', 'status', 'trial_ends_at', 'current_period_start', 'current_period_end',
    'canceled_at', 'payment_method',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'payment_method' => PaymentMethod::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * Estado real para el panel: una prueba o un periodo vencido se muestra como `expired`
     * aunque la columna siga diciendo `trialing`/`active`.
     *
     * @return Attribute<string, never>
     */
    protected function effectiveStatus(): Attribute
    {
        return Attribute::get(fn (): string => app(Billing::class)->effectiveStatus($this));
    }

    /**
     * @return MorphTo<User|Organization, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}

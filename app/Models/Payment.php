<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'amount', 'currency', 'exchange_rate', 'method', 'reference', 'paid_on', 'status', 'notes',
    'reviewed_at', 'reviewed_by',
])]
class Payment extends Model implements HasMedia
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('proof')->singleFile();
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Monto convertido a la moneda del plan (USD por defecto).
     * `exchange_rate` = unidades de la moneda del pago por 1 USD; 1 cuando coinciden.
     *
     * @return Attribute<float, never>
     */
    protected function amountBase(): Attribute
    {
        return Attribute::get(function (): float {
            $rate = (float) $this->exchange_rate;

            if ($rate <= 0) {
                $rate = 1.0;
            }

            return round((float) $this->amount / $rate, 2);
        });
    }
}

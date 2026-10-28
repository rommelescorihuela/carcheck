<?php

namespace App\Models;

use Database\Factories\MaintenanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'maintenance_type_id', 'performed_on', 'odometer_km', 'provider', 'notes',
    'next_due_km', 'next_due_date', 'currency',
])]
class MaintenanceRecord extends Model implements HasMedia
{
    /** @use HasFactory<MaintenanceRecordFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'next_due_date' => 'date',
            'odometer_km' => 'integer',
            'next_due_km' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<MaintenanceType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class, 'maintenance_type_id');
    }

    /**
     * @return HasMany<MaintenanceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceItem::class);
    }

    /**
     * Total = Σ cantidad × precio unitario.
     *
     * @return Attribute<float, never>
     */
    protected function total(): Attribute
    {
        return Attribute::get(fn (): float => $this->items->sum(
            fn (MaintenanceItem $item): float => (float) $item->quantity * (float) $item->unit_price
        ));
    }
}

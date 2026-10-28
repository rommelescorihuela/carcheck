<?php

namespace App\Models;

use Database\Factories\FuelEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'filled_on', 'odometer_km', 'liters', 'unit_price', 'full_tank', 'station', 'notes', 'currency',
])]
class FuelEntry extends Model
{
    /** @use HasFactory<FuelEntryFactory> */
    use HasFactory;

    /**
     * Métricas calculadas por `FuelConsumption`. No se persisten.
     *
     * @var array<string, mixed>|null
     */
    public ?array $metrics = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filled_on' => 'date',
            'odometer_km' => 'integer',
            'liters' => 'decimal:2',
            'unit_price' => 'decimal:3',
            'full_tank' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Total derivado de la carga (litros × precio unitario). Nunca se almacena.
     *
     * @return Attribute<float, never>
     */
    protected function total(): Attribute
    {
        return Attribute::get(fn (): float => round(
            (float) $this->liters * (float) $this->unit_price,
            2,
        ));
    }
}

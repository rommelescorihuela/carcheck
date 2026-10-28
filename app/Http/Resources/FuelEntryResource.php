<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FuelEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'filled_on' => $this->filled_on?->toDateString(),
            'odometer_km' => $this->odometer_km,
            'liters' => $this->liters,
            'unit_price' => $this->unit_price,
            'total' => $this->total,
            'currency' => $this->currency,
            'full_tank' => (bool) $this->full_tank,
            'station' => $this->station,
            'notes' => $this->notes,
            'distance_km' => $this->metrics['distance_km'] ?? null,
            'km_per_liter' => $this->metrics['km_per_liter'] ?? null,
            'cost_per_km' => $this->metrics['cost_per_km'] ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

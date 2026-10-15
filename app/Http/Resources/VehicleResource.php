<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'plate' => $this->plate,
            'vin' => $this->vin,
            'type' => $this->type->value,
            'fuel_type' => $this->fuel_type->value,
            'odometer_km' => $this->odometer_km,
            'color' => $this->color,
            'notes' => $this->notes,
            'photo_url' => $this->photo_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

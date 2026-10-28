<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenancePlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'maintenance_type_id' => $this->maintenance_type_id,
            'interval_km' => $this->interval_km,
            'interval_months' => $this->interval_months,
            'type' => new MaintenanceTypeResource($this->whenLoaded('type')),
        ];
    }
}

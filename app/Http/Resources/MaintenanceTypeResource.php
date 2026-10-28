<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'interval_km' => $this->interval_km,
            'interval_months' => $this->interval_months,
            'is_global' => $this->isGlobal(),
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
        ];
    }
}

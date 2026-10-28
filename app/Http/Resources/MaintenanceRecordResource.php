<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceRecordResource extends JsonResource
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
            'type' => new MaintenanceTypeResource($this->whenLoaded('type')),
            'performed_on' => $this->performed_on?->toDateString(),
            'odometer_km' => $this->odometer_km,
            'provider' => $this->provider,
            'notes' => $this->notes,
            'next_due_km' => $this->next_due_km,
            'next_due_date' => $this->next_due_date?->toDateString(),
            'currency' => $this->currency,
            'total' => $this->total,
            'items' => MaintenanceItemResource::collection($this->whenLoaded('items')),
            'attachments' => $this->getMedia('attachments')->map(fn ($media) => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'url' => $media->getUrl(),
            ])->values(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

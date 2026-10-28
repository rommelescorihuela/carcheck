<?php

namespace App\Http\Resources;

use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reminder
 */
class ReminderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vehicle = $this->vehicle;

        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'title' => $this->title,
            'body' => $this->body,
            'due_on' => $this->due_on?->toDateString(),
            'due_km' => $this->due_km,
            'km_left' => $this->kmLeft($vehicle),
            'days_left' => $this->daysLeft(),
            'status' => $this->status($vehicle),
            'vehicle' => [
                'id' => $vehicle->id,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'plate' => $vehicle->plate,
            ],
            'url' => '/recordatorios',
            'created_at' => $this->created_at,
        ];
    }
}

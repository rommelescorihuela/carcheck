<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'interval' => $this->interval->value,
            'interval_label' => $this->interval->label(),
            'trial_days' => $this->trial_days,
            'max_vehicles' => $this->max_vehicles,
            'max_members' => $this->max_members,
            'sort' => $this->sort,
        ];
    }
}

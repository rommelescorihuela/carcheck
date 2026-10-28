<?php

namespace App\Http\Resources;

use App\Services\DocumentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = app(DocumentStatus::class)->for($this->expires_on);
        $file = $this->getFirstMedia('file');

        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'name' => $this->name,
            'issuer' => $this->issuer,
            'reference' => $this->reference,
            'issued_on' => $this->issued_on?->toDateString(),
            'expires_on' => $this->expires_on?->toDateString(),
            'notes' => $this->notes,
            'status' => $state['status'],
            'days_left' => $state['days_left'],
            'file' => $file === null ? null : [
                'id' => $file->id,
                'file_name' => $file->file_name,
                'url' => $file->getUrl(),
                'mime_type' => $file->mime_type,
                'size' => $file->size,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

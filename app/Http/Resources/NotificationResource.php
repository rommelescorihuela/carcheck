<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        return [
            'id' => $this->id,
            'category' => $data['category'] ?? 'system',
            'category_label' => $data['category_label'] ?? 'Sistema',
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'url' => $data['url'] ?? null,
            'payload' => $data['payload'] ?? [],
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at,
        ];
    }
}

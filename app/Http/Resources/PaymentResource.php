<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $proof = $this->getFirstMedia('proof');

        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,
            'amount_base' => $this->amount_base,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'reference' => $this->reference,
            'paid_on' => $this->paid_on?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'notes' => $this->notes,
            'proof' => $proof === null ? null : [
                'id' => $proof->id,
                'file_name' => $proof->file_name,
                'url' => $proof->getUrl(),
            ],
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at,
        ];
    }
}

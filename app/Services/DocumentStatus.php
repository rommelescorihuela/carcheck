<?php

namespace App\Services;

use App\Models\Vehicle;
use Carbon\CarbonInterface;

/**
 * Estado de vencimiento de un documento. Se deriva de `expires_on` en cada lectura: una columna
 * `status` quedaría obsoleta al día siguiente.
 */
class DocumentStatus
{
    /** Días de antelación con los que un documento pasa a "por vencer". */
    public const WARNING_DAYS = 30;

    /**
     * @return array{status: string, days_left: int|null}
     */
    public function for(?CarbonInterface $expiresOn): array
    {
        if ($expiresOn === null) {
            return ['status' => 'valid', 'days_left' => null];
        }

        $daysLeft = (int) now()->startOfDay()->diffInDays($expiresOn->copy()->startOfDay(), false);

        return [
            'status' => match (true) {
                $daysLeft < 0 => 'expired',
                $daysLeft <= self::WARNING_DAYS => 'expiring',
                default => 'valid',
            },
            'days_left' => $daysLeft,
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     valid: int,
     *     expiring: int,
     *     expired: int,
     *     next_expiration: array<string, mixed>|null
     * }
     */
    public function summary(Vehicle $vehicle): array
    {
        $documents = $vehicle->documents()->get();

        $counts = ['valid' => 0, 'expiring' => 0, 'expired' => 0];
        $next = null;

        foreach ($documents as $document) {
            $state = $this->for($document->expires_on);
            $counts[$state['status']]++;

            if ($state['days_left'] === null || $state['days_left'] < 0) {
                continue;
            }

            if ($next === null || $document->expires_on->lt($next['document']->expires_on)) {
                $next = ['document' => $document, 'days_left' => $state['days_left']];
            }
        }

        return [
            'total' => $documents->count(),
            'valid' => $counts['valid'],
            'expiring' => $counts['expiring'],
            'expired' => $counts['expired'],
            'next_expiration' => $next === null ? null : [
                'id' => $next['document']->id,
                'name' => $next['document']->name,
                'kind' => $next['document']->kind->value,
                'kind_label' => $next['document']->kind->label(),
                'expires_on' => $next['document']->expires_on->toDateString(),
                'days_left' => $next['days_left'],
            ],
        ];
    }
}

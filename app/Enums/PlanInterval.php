<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum PlanInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensual',
            self::Yearly => 'Anual',
        };
    }

    /**
     * Fin del periodo que empieza en `$start` según el intervalo.
     */
    public function periodEnd(CarbonInterface $start): CarbonInterface
    {
        return match ($this) {
            self::Monthly => $start->copy()->addMonth(),
            self::Yearly => $start->copy()->addYear(),
        };
    }
}

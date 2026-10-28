<?php

namespace App\Services;

use App\Models\FuelEntry;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Calcula distancia, rendimiento (km/l) y costo por km de las cargas de combustible.
 *
 * Regla: el rendimiento se mide entre **tanques llenos**. Las cargas parciales acumulan litros y
 * se liquidan en la carga que vuelve a llenar el tanque; sin eso el km/l saldría inflado.
 */
class FuelConsumption
{
    /**
     * @return array<int, array{
     *     entry: FuelEntry,
     *     distance_km: int|null,
     *     consumption_distance_km: int|null,
     *     liters_since_full: float|null,
     *     km_per_liter: float|null,
     *     cost_per_km: float|null
     * }>
     */
    public function forVehicle(Vehicle $vehicle): array
    {
        $entries = $this->entries($vehicle);

        $rows = [];
        $previousOdometer = null;
        $lastFullOdometer = null;
        $litersSinceFull = 0.0;

        foreach ($entries as $entry) {
            $odometer = (int) $entry->odometer_km;
            $distance = $previousOdometer === null ? null : $odometer - $previousOdometer;
            $litersSinceFull += (float) $entry->liters;

            $consumptionDistance = null;
            $kmPerLiter = null;

            if ($entry->full_tank) {
                if ($lastFullOdometer !== null) {
                    $consumptionDistance = $odometer - $lastFullOdometer;

                    if ($consumptionDistance > 0 && $litersSinceFull > 0) {
                        $kmPerLiter = round($consumptionDistance / $litersSinceFull, 2);
                    }
                }

                $lastFullOdometer = $odometer;
            }

            $rows[] = [
                'entry' => $entry,
                'distance_km' => $distance,
                'consumption_distance_km' => $consumptionDistance,
                'liters_since_full' => $entry->full_tank ? round($litersSinceFull, 2) : null,
                'km_per_liter' => $kmPerLiter,
                'cost_per_km' => $this->ratio($entry->total, $distance),
            ];

            if ($entry->full_tank) {
                $litersSinceFull = 0.0;
            }

            $previousOdometer = $odometer;
        }

        return $rows;
    }

    /**
     * @return array{
     *     entries: int,
     *     total_liters: float,
     *     total_cost: float,
     *     distance_km: int,
     *     km_per_liter: float|null,
     *     cost_per_km: float|null,
     *     currency: string,
     *     last_filled_on: string|null
     * }
     */
    public function summary(Vehicle $vehicle): array
    {
        $rows = $this->forVehicle($vehicle);

        if ($rows === []) {
            return [
                'entries' => 0,
                'total_liters' => 0.0,
                'total_cost' => 0.0,
                'distance_km' => 0,
                'km_per_liter' => null,
                'cost_per_km' => null,
                'currency' => $this->currencyOf($vehicle),
                'last_filled_on' => null,
            ];
        }

        $totalLiters = 0.0;
        $totalCost = 0.0;
        $consumedDistance = 0;
        $consumedLiters = 0.0;
        $odometers = [];
        $lastFilledOn = null;

        foreach ($rows as $row) {
            /** @var FuelEntry $entry */
            $entry = $row['entry'];

            $totalLiters += (float) $entry->liters;
            $totalCost += $entry->total;
            $odometers[] = (int) $entry->odometer_km;

            if ($row['consumption_distance_km'] !== null && $row['liters_since_full'] !== null) {
                $consumedDistance += $row['consumption_distance_km'];
                $consumedLiters += $row['liters_since_full'];
            }

            $filledOn = $entry->filled_on?->toDateString();
            if ($filledOn !== null && ($lastFilledOn === null || $filledOn > $lastFilledOn)) {
                $lastFilledOn = $filledOn;
            }
        }

        $range = max($odometers) - min($odometers);

        return [
            'entries' => count($rows),
            'total_liters' => round($totalLiters, 2),
            'total_cost' => round($totalCost, 2),
            'distance_km' => $range,
            'km_per_liter' => $consumedDistance > 0 && $consumedLiters > 0
                ? round($consumedDistance / $consumedLiters, 2)
                : null,
            'cost_per_km' => $range > 0 ? round($totalCost / $range, 4) : null,
            'currency' => $this->currencyOf($vehicle),
            'last_filled_on' => $lastFilledOn,
        ];
    }

    /**
     * @return Collection<int, FuelEntry>
     */
    private function entries(Vehicle $vehicle): Collection
    {
        return $vehicle->fuelEntries()
            ->orderBy('odometer_km')
            ->orderBy('filled_on')
            ->orderBy('id')
            ->get();
    }

    private function ratio(float $numerator, ?int $denominator): ?float
    {
        if ($denominator === null || $denominator <= 0) {
            return null;
        }

        return round($numerator / $denominator, 4);
    }

    private function currencyOf(Vehicle $vehicle): string
    {
        return $vehicle->owner->currency ?? 'USD';
    }
}

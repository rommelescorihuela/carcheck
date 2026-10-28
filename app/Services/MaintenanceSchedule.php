<?php

namespace App\Services;

use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

class MaintenanceSchedule
{
    private const SOON_KM = 500;

    private const SOON_DAYS = 15;

    /**
     * Próximo servicio por cada tipo con plan definido en el vehículo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dueFor(Vehicle $vehicle): array
    {
        $plans = $vehicle->maintenancePlans()->with('type')->get();
        $results = [];

        foreach ($plans as $plan) {
            $last = $vehicle->maintenanceRecords()
                ->where('maintenance_type_id', $plan->maintenance_type_id)
                ->orderByDesc('performed_on')
                ->orderByDesc('odometer_km')
                ->first();

            if ($last === null) {
                continue;
            }

            $manual = $last->next_due_km !== null || $last->next_due_date !== null;

            $dueKm = $last->next_due_km
                ?? ($plan->interval_km !== null ? $last->odometer_km + $plan->interval_km : null);

            $dueDate = $last->next_due_date
                ?? ($plan->interval_months !== null ? $last->performed_on->copy()->addMonths($plan->interval_months) : null);

            $results[] = [
                'maintenance_type' => [
                    'id' => $plan->type->id,
                    'name' => $plan->type->name,
                ],
                'due_km' => $dueKm,
                'due_date' => $dueDate?->toDateString(),
                'status' => $this->status($vehicle, $dueKm, $dueDate),
                'source' => $manual ? 'manual' : 'plan',
            ];
        }

        return $results;
    }

    private function status(Vehicle $vehicle, ?int $dueKm, ?Carbon $dueDate): string
    {
        $due = false;
        $soon = false;

        if ($dueKm !== null) {
            $remaining = $dueKm - $vehicle->odometer_km;
            if ($remaining <= 0) {
                $due = true;
            } elseif ($remaining <= self::SOON_KM) {
                $soon = true;
            }
        }

        if ($dueDate !== null) {
            if ($dueDate->isPast() || $dueDate->isToday()) {
                $due = true;
            } elseif (CarbonImmutable::now()->diffInDays($dueDate) <= self::SOON_DAYS) {
                $soon = true;
            }
        }

        return $due ? 'due' : ($soon ? 'soon' : 'ok');
    }
}

<?php

namespace App\Services;

use App\Models\Document;
use App\Models\FuelEntry;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Vista consolidada de una flota: gasto del periodo, combustible, documentos vencidos y
 * servicios pendientes. Reutiliza `ExpenseReport` para que los totales coincidan con Reportes.
 */
class FleetSummary
{
    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array<string, mixed>
     */
    public function build(Collection $vehicles, Carbon $from, Carbon $to): array
    {
        $ids = $vehicles->pluck((new Vehicle)->getKeyName())->all();

        $report = app(ExpenseReport::class)->build($vehicles, [], $from, $to);
        $documents = $this->documents($ids);
        $fuel = $this->fuel($ids, $from, $to);
        $maintenance = $this->maintenance($ids, $from, $to);
        $due = $this->due($vehicles);

        $rows = [];
        $spentByVehicle = collect($report['vehicles'])->keyBy(fn (array $row): int => (int) $row['vehicle']['id']);

        foreach ($vehicles as $vehicle) {
            $spend = $spentByVehicle->get((int) $vehicle->getKey());
            $vehicleDocuments = $documents['by_vehicle'][$vehicle->getKey()] ?? null;
            $vehicleDue = $due['by_vehicle'][$vehicle->getKey()] ?? null;

            $rows[] = [
                'vehicle' => [
                    'id' => (int) $vehicle->getKey(),
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'plate' => $vehicle->plate,
                    'odometer_km' => $vehicle->odometer_km,
                ],
                'currency' => $spend['currency'] ?? ($vehicle->owner?->currency ?? 'USD'),
                'fuel' => [
                    'entries' => $spend['fuel_entries'] ?? 0,
                    'cost' => $spend['fuel'] ?? 0.0,
                ],
                'maintenance' => [
                    'records' => $spend['maintenance_entries'] ?? 0,
                    'cost' => $spend['maintenance'] ?? 0.0,
                ],
                'total' => $spend['total'] ?? 0.0,
                'documents' => [
                    'expired' => $vehicleDocuments['expired'] ?? 0,
                    'expiring' => $vehicleDocuments['expiring'] ?? 0,
                ],
                'maintenance_due' => $vehicleDue['due'] ?? 0,
                'maintenance_soon' => $vehicleDue['soon'] ?? 0,
            ];
        }

        // Lo que el dueño de la flota necesita ver primero: lo que ya venció.
        usort($rows, fn (array $left, array $right): int => [$right['documents']['expired'], $right['maintenance_due'], $right['total']]
            <=> [$left['documents']['expired'], $left['maintenance_due'], $left['total']]);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'vehicles' => [
                'total' => $vehicles->count(),
                'with_expired_documents' => $documents['vehicles_expired'],
                'with_due_maintenance' => $due['vehicles_due'],
                'without_activity' => collect($rows)->where('total', 0.0)->count(),
            ],
            'totals' => $report['totals'],
            'fuel' => $fuel,
            'maintenance' => $maintenance,
            'documents' => [
                'total' => $documents['total'],
                'expired' => $documents['expired'],
                'expiring' => $documents['expiring'],
            ],
            'alerts' => $this->alerts($documents, $due),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<int, int>  $ids
     * @return array{total: int, expired: int, expiring: int, vehicles_expired: int, by_vehicle: array<int, array{expired: int, expiring: int}>, alerts: array<int, array<string, mixed>>}
     */
    private function documents(array $ids): array
    {
        $status = app(DocumentStatus::class);
        $totals = ['total' => 0, 'expired' => 0, 'expiring' => 0];
        $byVehicle = [];
        $alerts = [];

        if ($ids === []) {
            return [...$totals, 'vehicles_expired' => 0, 'by_vehicle' => [], 'alerts' => []];
        }

        $documents = Document::query()->whereIn('vehicle_id', $ids)->with('vehicle')->get();

        foreach ($documents as $document) {
            $state = $status->for($document->expires_on);
            $totals['total']++;
            $byVehicle[$document->vehicle_id] ??= ['expired' => 0, 'expiring' => 0];

            if ($state['status'] === 'valid') {
                continue;
            }

            $totals[$state['status']]++;
            $byVehicle[$document->vehicle_id][$state['status']]++;

            if ($state['status'] === 'expired') {
                $alerts[] = [
                    'kind' => 'document',
                    'vehicle_id' => (int) $document->vehicle_id,
                    'vehicle' => $this->vehicleLabel($document->vehicle),
                    'label' => $document->name,
                    'days_left' => $state['days_left'],
                ];
            }
        }

        return [
            ...$totals,
            'vehicles_expired' => collect($byVehicle)->filter(fn (array $row): bool => $row['expired'] > 0)->count(),
            'by_vehicle' => $byVehicle,
            'alerts' => $alerts,
        ];
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<string, mixed>
     */
    private function fuel(array $ids, Carbon $from, Carbon $to): array
    {
        if ($ids === []) {
            return ['entries' => 0, 'liters' => 0.0, 'costs' => []];
        }

        $entries = FuelEntry::query()
            ->whereIn('vehicle_id', $ids)
            ->whereBetween('filled_on', [$from->toDateString(), $to->toDateString()])
            ->get();

        $costs = [];

        foreach ($entries as $entry) {
            $costs[$entry->currency] ??= ['currency' => $entry->currency, 'liters' => 0.0, 'cost' => 0.0, 'avg_price' => 0.0];
            $costs[$entry->currency]['liters'] = round($costs[$entry->currency]['liters'] + (float) $entry->liters, 2);
            $costs[$entry->currency]['cost'] = round($costs[$entry->currency]['cost'] + $entry->total, 2);
        }

        foreach ($costs as $currency => $row) {
            $costs[$currency]['avg_price'] = $row['liters'] > 0
                ? round($row['cost'] / $row['liters'], 3)
                : 0.0;
        }

        ksort($costs);

        return [
            'entries' => $entries->count(),
            'liters' => round((float) $entries->sum('liters'), 2),
            'costs' => array_values($costs),
        ];
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<string, mixed>
     */
    private function maintenance(array $ids, Carbon $from, Carbon $to): array
    {
        if ($ids === []) {
            return ['records' => 0, 'costs' => []];
        }

        $records = MaintenanceRecord::query()
            ->whereIn('vehicle_id', $ids)
            ->whereBetween('performed_on', [$from->toDateString(), $to->toDateString()])
            ->with('items')
            ->get();

        $costs = [];

        foreach ($records as $record) {
            $costs[$record->currency] ??= ['currency' => $record->currency, 'cost' => 0.0];
            $costs[$record->currency]['cost'] = round($costs[$record->currency]['cost'] + (float) $record->total, 2);
        }

        ksort($costs);

        return [
            'records' => $records->count(),
            'costs' => array_values($costs),
        ];
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array{vehicles_due: int, by_vehicle: array<int, array{due: int, soon: int}>, alerts: array<int, array<string, mixed>>}
     */
    private function due(Collection $vehicles): array
    {
        $schedule = app(MaintenanceSchedule::class);
        $byVehicle = [];
        $alerts = [];

        foreach ($vehicles as $vehicle) {
            $rows = $schedule->dueFor($vehicle);
            $due = 0;
            $soon = 0;

            foreach ($rows as $row) {
                if ($row['status'] === 'due') {
                    $due++;
                    $alerts[] = [
                        'kind' => 'maintenance',
                        'vehicle_id' => (int) $vehicle->getKey(),
                        'vehicle' => $this->vehicleLabel($vehicle),
                        'label' => $row['maintenance_type']['name'],
                        'days_left' => null,
                    ];
                } elseif ($row['status'] === 'soon') {
                    $soon++;
                }
            }

            if ($due > 0 || $soon > 0) {
                $byVehicle[$vehicle->getKey()] = ['due' => $due, 'soon' => $soon];
            }
        }

        return [
            'vehicles_due' => collect($byVehicle)->filter(fn (array $row): bool => $row['due'] > 0)->count(),
            'by_vehicle' => $byVehicle,
            'alerts' => $alerts,
        ];
    }

    /**
     * @param  array<string, mixed>  $documents
     * @param  array<string, mixed>  $due
     * @return array<int, array<string, mixed>>
     */
    private function alerts(array $documents, array $due): array
    {
        $alerts = [...$documents['alerts'], ...$due['alerts']];

        usort($alerts, fn (array $left, array $right): int => ($left['days_left'] ?? PHP_INT_MAX) <=> ($right['days_left'] ?? PHP_INT_MAX));

        return array_slice($alerts, 0, 12);
    }

    private function vehicleLabel(?Vehicle $vehicle): string
    {
        return trim(sprintf('%s %s', (string) $vehicle?->make, (string) $vehicle?->model));
    }
}

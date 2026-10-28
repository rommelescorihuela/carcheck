<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\FuelEntry;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Informe de gastos de los vehículos. No convierte monedas: cada gasto conserva la suya y los
 * totales se agrupan por moneda. La agregación se hace en PHP para que el resultado sea idéntico en
 * SQLite (tests) y PostgreSQL (producción).
 */
class ExpenseReport
{
    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @param  array<int, User|Organization>  $owners  propietarios cuya suscripción se informa
     * @return array{
     *     totals: array<int, array<string, mixed>>,
     *     vehicles: array<int, array<string, mixed>>,
     *     monthly: array<int, array<string, mixed>>,
     *     billing: array<int, array<string, mixed>>
     * }
     */
    public function build(Collection $vehicles, array $owners, Carbon $from, Carbon $to): array
    {
        $expenses = $this->expenses($vehicles, $from, $to);

        return [
            'totals' => $this->totals($expenses),
            'vehicles' => $this->byVehicle($vehicles, $expenses),
            'monthly' => $this->byMonth($expenses),
            'billing' => $this->billing($owners, $from, $to),
        ];
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array<int, array{vehicle_id: int, currency: string, month: string, kind: string, amount: float}>
     */
    private function expenses(Collection $vehicles, Carbon $from, Carbon $to): array
    {
        if ($vehicles->isEmpty()) {
            return [];
        }

        $range = [$from->toDateString(), $to->toDateString()];
        $expenses = [];

        $records = MaintenanceRecord::query()
            ->whereIn('vehicle_id', $vehicles->pluck((new Vehicle)->getKeyName()))
            ->whereBetween('performed_on', $range)
            ->with('items')
            ->get();

        foreach ($records as $record) {
            $expenses[] = [
                'vehicle_id' => (int) $record->vehicle_id,
                'currency' => $record->currency,
                'month' => $record->performed_on->format('Y-m'),
                'kind' => 'maintenance',
                'amount' => (float) $record->total,
            ];
        }

        $entries = FuelEntry::query()
            ->whereIn('vehicle_id', $vehicles->pluck((new Vehicle)->getKeyName()))
            ->whereBetween('filled_on', $range)
            ->get();

        foreach ($entries as $entry) {
            $expenses[] = [
                'vehicle_id' => (int) $entry->vehicle_id,
                'currency' => $entry->currency,
                'month' => $entry->filled_on->format('Y-m'),
                'kind' => 'fuel',
                'amount' => (float) $entry->total,
            ];
        }

        return $expenses;
    }

    /**
     * @param  array<int, array<string, mixed>>  $expenses
     * @return array<int, array<string, mixed>>
     */
    private function totals(array $expenses): array
    {
        $rows = [];

        foreach ($expenses as $expense) {
            $rows[$expense['currency']] ??= $this->emptyRow($expense['currency']);
            $this->add($rows[$expense['currency']], $expense);
        }

        ksort($rows);

        return array_values($rows);
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @param  array<int, array<string, mixed>>  $expenses
     * @return array<int, array<string, mixed>>
     */
    private function byVehicle(Collection $vehicles, array $expenses): array
    {
        $catalog = $vehicles->keyBy('id');
        $rows = [];

        foreach ($expenses as $expense) {
            $key = $expense['vehicle_id'].'|'.$expense['currency'];
            $rows[$key] ??= [
                'vehicle' => $this->vehicleSummary($catalog->get($expense['vehicle_id'])),
                ...$this->emptyRow($expense['currency']),
            ];
            $this->add($rows[$key], $expense);
        }

        uasort($rows, fn (array $left, array $right): int => $right['total'] <=> $left['total']
            ?: [$left['vehicle']['make'], $left['vehicle']['model'], $left['currency']]
            <=> [$right['vehicle']['make'], $right['vehicle']['model'], $right['currency']]);

        return array_values($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $expenses
     * @return array<int, array<string, mixed>>
     */
    private function byMonth(array $expenses): array
    {
        $rows = [];

        foreach ($expenses as $expense) {
            $key = $expense['month'].'|'.$expense['currency'];
            $rows[$key] ??= [
                'month' => $expense['month'],
                ...$this->emptyRow($expense['currency']),
            ];
            $this->add($rows[$key], $expense);
        }

        uasort($rows, fn (array $left, array $right): int => [$left['month'], $left['currency']]
            <=> [$right['month'], $right['currency']]);

        return array_values($rows);
    }

    /**
     * Pagos aprobados del propietario en el periodo, en la moneda del plan de cada suscripción.
     *
     * @param  array<int, User|Organization>  $owners
     * @return array<int, array{currency: string, payments: int, total: float}>
     */
    private function billing(array $owners, Carbon $from, Carbon $to): array
    {
        if ($owners === []) {
            return [];
        }

        $payments = Payment::query()
            ->where('status', PaymentStatus::Approved)
            ->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])
            ->whereHas('subscription', function (Builder $query) use ($owners): void {
                $query->where(function (Builder $query) use ($owners): void {
                    foreach ($owners as $owner) {
                        $query->orWhere(fn (Builder $query) => $query
                            ->where('owner_type', $owner->getMorphClass())
                            ->where('owner_id', $owner->getKey()));
                    }
                });
            })
            ->with('subscription.plan')
            ->get();

        $rows = [];

        foreach ($payments as $payment) {
            $currency = $payment->subscription?->plan?->currency ?? 'USD';
            $rows[$currency] ??= ['currency' => $currency, 'payments' => 0, 'total' => 0.0];

            $rows[$currency]['payments']++;
            $rows[$currency]['total'] = round($rows[$currency]['total'] + $payment->amount_base, 2);
        }

        ksort($rows);

        return array_values($rows);
    }

    /**
     * @return array{currency: string, maintenance: float, fuel: float, total: float, maintenance_entries: int, fuel_entries: int}
     */
    private function emptyRow(string $currency): array
    {
        return [
            'currency' => $currency,
            'maintenance' => 0.0,
            'fuel' => 0.0,
            'total' => 0.0,
            'maintenance_entries' => 0,
            'fuel_entries' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $expense
     */
    private function add(array &$row, array $expense): void
    {
        $kind = $expense['kind'];

        $row[$kind] = round($row[$kind] + $expense['amount'], 2);
        $row['total'] = round($row['total'] + $expense['amount'], 2);
        $row[$kind.'_entries']++;
    }

    /**
     * @return array{id: int, make: string, model: string, plate: string|null}
     */
    private function vehicleSummary(?Vehicle $vehicle): array
    {
        return [
            'id' => (int) $vehicle?->getKey(),
            'make' => (string) $vehicle?->make,
            'model' => (string) $vehicle?->model,
            'plate' => $vehicle?->plate,
        ];
    }
}

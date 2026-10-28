<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Serializa el informe de gastos a CSV. Reutiliza el mismo array que la API para que ambos
 * formatos no puedan divergir. Los números llevan punto decimal y el archivo empieza por BOM
 * para que Excel respete los acentos.
 */
class ExpenseReportCsv
{
    /**
     * @param  array{totals: array<int, array<string, mixed>>, vehicles: array<int, array<string, mixed>>, monthly: array<int, array<string, mixed>>, billing: array<int, array<string, mixed>>}  $report
     */
    public function build(array $report, Carbon $from, Carbon $to): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");

        $this->put($handle, ['Informe de gastos']);
        $this->put($handle, ['Periodo', $from->toDateString(), $to->toDateString()]);
        $this->blank($handle);

        $this->put($handle, ['Totales']);
        $this->put($handle, [
            'Moneda', 'Mantenimiento', 'Combustible', 'Total',
            'Registros de mantenimiento', 'Registros de combustible',
        ]);

        foreach ($report['totals'] as $row) {
            $this->put($handle, [
                $row['currency'],
                $this->money($row['maintenance']),
                $this->money($row['fuel']),
                $this->money($row['total']),
                $row['maintenance_entries'],
                $row['fuel_entries'],
            ]);
        }

        $this->blank($handle);

        $this->put($handle, ['Vehículos']);
        $this->put($handle, [
            'Vehículo', 'Matrícula', 'Moneda', 'Mantenimiento', 'Combustible', 'Total',
            'Registros de mantenimiento', 'Registros de combustible',
        ]);

        foreach ($report['vehicles'] as $row) {
            $this->put($handle, [
                $this->vehicleLabel($row['vehicle']),
                $row['vehicle']['plate'],
                $row['currency'],
                $this->money($row['maintenance']),
                $this->money($row['fuel']),
                $this->money($row['total']),
                $row['maintenance_entries'],
                $row['fuel_entries'],
            ]);
        }

        $this->blank($handle);

        $this->put($handle, ['Meses']);
        $this->put($handle, [
            'Mes', 'Moneda', 'Mantenimiento', 'Combustible', 'Total',
            'Registros de mantenimiento', 'Registros de combustible',
        ]);

        foreach ($report['monthly'] as $row) {
            $this->put($handle, [
                $row['month'],
                $row['currency'],
                $this->money($row['maintenance']),
                $this->money($row['fuel']),
                $this->money($row['total']),
                $row['maintenance_entries'],
                $row['fuel_entries'],
            ]);
        }

        $this->blank($handle);

        $this->put($handle, ['Suscripciones']);
        $this->put($handle, ['Moneda', 'Pagos aprobados', 'Total']);

        foreach ($report['billing'] as $row) {
            $this->put($handle, [
                $row['currency'],
                $row['payments'],
                $this->money($row['total']),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * @param  resource  $handle
     * @param  array<int, mixed>  $row
     */
    private function put($handle, array $row): void
    {
        fputcsv($handle, array_map($this->cell(...), $row), ',', '"', '');
    }

    /**
     * @param  resource  $handle
     */
    private function blank($handle): void
    {
        fwrite($handle, "\n");
    }

    /**
     * Evita que un valor escrito por el usuario se ejecute como fórmula al abrir el CSV.
     */
    private function cell(mixed $value): string|int|float
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * @param  array{make: string, model: string}  $vehicle
     */
    private function vehicleLabel(array $vehicle): string
    {
        return trim($vehicle['make'].' '.$vehicle['model']);
    }
}

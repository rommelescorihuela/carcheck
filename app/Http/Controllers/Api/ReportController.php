<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExpenseReport;
use App\Services\ExpenseReportCsv;
use App\Services\VehicleScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function expenses(Request $request): JsonResponse
    {
        $report = $this->report($request);

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $report['from']->toDateString(),
                    'to' => $report['to']->toDateString(),
                ],
                ...$report['data'],
            ],
        ]);
    }

    /**
     * Mismo informe que `expenses`, en CSV. La descarga va autenticada como el resto de la API.
     */
    public function export(Request $request): Response
    {
        $report = $this->report($request);

        $csv = app(ExpenseReportCsv::class)->build($report['data'], $report['from'], $report['to']);

        $filename = sprintf(
            'gastos_%s_%s.csv',
            $report['from']->toDateString(),
            $report['to']->toDateString(),
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Valida, resuelve el ámbito y construye el informe una sola vez para JSON y CSV.
     *
     * @return array{from: Carbon, to: Carbon, data: array<string, mixed>}
     */
    private function report(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'vehicle_id' => ['nullable', 'integer'],
            'organization_id' => ['nullable', 'integer'],
            'scope' => ['nullable', 'in:personal'],
        ]);

        // Por defecto, el año en curso: es la consulta habitual y evita un informe enorme.
        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->startOfYear();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->startOfDay() : today();

        $scope = app(VehicleScope::class);
        $vehicles = $scope->for($request);

        if (isset($validated['vehicle_id'])) {
            $vehicles->whereKey($validated['vehicle_id']);
        }

        $models = $vehicles->get();

        // El filtro por vehículo ajeno o inexistente es un 404: no se revela su existencia.
        abort_if(isset($validated['vehicle_id']) && $models->isEmpty(), 404);

        return [
            'from' => $from,
            'to' => $to,
            'data' => app(ExpenseReport::class)->build(
                $models,
                $scope->owners($request),
                $from,
                $to,
            ),
        ];
    }
}

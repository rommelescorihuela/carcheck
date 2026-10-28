<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExpenseReport;
use App\Services\VehicleScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function expenses(Request $request): JsonResponse
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

        $report = app(ExpenseReport::class)->build(
            $models,
            $scope->owners($request),
            $from,
            $to,
        );

        return response()->json([
            'data' => [
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                ...$report,
            ],
        ]);
    }
}

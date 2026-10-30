<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FleetSummary;
use App\Services\VehicleScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FleetController extends Controller
{
    /**
     * Vista consolidada de la flota del ámbito pedido (organización o todo lo accesible).
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        // Por defecto, el mes en curso: es la pregunta habitual de un dueño de flota.
        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->startOfMonth();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->startOfDay() : today();

        $vehicles = app(VehicleScope::class)->for($request)->with('owner')->get();

        return response()->json([
            'data' => app(FleetSummary::class)->build($vehicles, $from, $to),
        ]);
    }
}

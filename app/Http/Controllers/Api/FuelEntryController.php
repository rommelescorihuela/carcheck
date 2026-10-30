<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FuelEntryResource;
use App\Models\FuelEntry;
use App\Models\Vehicle;
use App\Services\FuelConsumption;
use App\Services\OdometerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class FuelEntryController extends Controller
{
    public function summary(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        return response()->json([
            'data' => app(FuelConsumption::class)->summary($vehicle),
        ]);
    }

    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        /** @var LengthAwarePaginator<int, FuelEntry> $entries */
        $entries = $vehicle->fuelEntries()
            ->with('user')
            ->orderByDesc('filled_on')
            ->orderByDesc('odometer_km')
            ->paginate(20);

        $this->attachMetrics($vehicle, $entries->getCollection()->all());

        return FuelEntryResource::collection($entries)->response();
    }

    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('log', $vehicle);

        $validated = $request->validate($this->rules());

        $entry = $vehicle->fuelEntries()->create($validated + [
            'user_id' => $request->user()->id,
            'currency' => $vehicle->owner->currency ?? 'USD',
        ]);

        app(OdometerService::class)->record($vehicle, $entry->odometer_km);

        $this->attachMetrics($vehicle, [$entry]);

        return (new FuelEntryResource($entry))->response()->setStatusCode(201);
    }

    public function show(Request $request, FuelEntry $entry): FuelEntryResource
    {
        $this->authorize('view', $entry);

        /** @var Vehicle $vehicle */
        $vehicle = $entry->vehicle;
        $this->attachMetrics($vehicle, [$entry]);

        return new FuelEntryResource($entry);
    }

    public function update(Request $request, FuelEntry $entry): FuelEntryResource
    {
        $this->authorize('update', $entry);

        $validated = $request->validate($this->rules());

        $entry->update($validated);

        /** @var Vehicle $vehicle */
        $vehicle = $entry->vehicle;
        app(OdometerService::class)->record($vehicle, $entry->odometer_km);

        $this->attachMetrics($vehicle, [$entry]);

        return new FuelEntryResource($entry);
    }

    public function destroy(Request $request, FuelEntry $entry): JsonResponse
    {
        $this->authorize('delete', $entry);

        $entry->delete();

        return response()->json(['message' => 'Carga de combustible eliminada.']);
    }

    /**
     * Adjunta a cada carga sus métricas de rendimiento sin consultar por entrada.
     *
     * @param  array<int, FuelEntry>  $entries
     */
    private function attachMetrics(Vehicle $vehicle, array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $rows = collect(app(FuelConsumption::class)->forVehicle($vehicle))
            ->keyBy(fn (array $row): int => (int) $row['entry']->id);

        foreach ($entries as $entry) {
            $entry->metrics = $rows->get((int) $entry->id);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'filled_on' => 'required|date',
            'odometer_km' => 'required|integer|min:0',
            'liters' => 'required|numeric|min:0.01|max:9999',
            'unit_price' => 'required|numeric|min:0',
            'full_tank' => 'boolean',
            'station' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaintenanceRecordResource;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Models\Vehicle;
use App\Services\MaintenanceSchedule;
use App\Services\OdometerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceRecordController extends Controller
{
    public function due(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        return response()->json([
            'data' => app(MaintenanceSchedule::class)->dueFor($vehicle),
        ]);
    }

    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        $records = $vehicle->maintenanceRecords()
            ->with(['items', 'type'])
            ->latest('performed_on')
            ->paginate(15);

        return MaintenanceRecordResource::collection($records)->response();
    }

    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $validated = $request->validate($this->rules());
        $this->assertTypeAvailable($request, $validated['maintenance_type_id'] ?? null);
        $items = $validated['items'] ?? [];
        unset($validated['items']);

        $record = $vehicle->maintenanceRecords()->create($validated + [
            'currency' => $vehicle->owner->currency ?? 'USD',
        ]);
        $record->items()->createMany($items);

        app(OdometerService::class)->record($vehicle, $record->odometer_km);

        return (new MaintenanceRecordResource($record->load(['items', 'type'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, MaintenanceRecord $record): MaintenanceRecordResource
    {
        $this->authorize('view', $record);

        return new MaintenanceRecordResource($record->load(['items', 'type']));
    }

    public function update(Request $request, MaintenanceRecord $record): MaintenanceRecordResource
    {
        $this->authorize('update', $record);

        $validated = $request->validate($this->rules());
        $this->assertTypeAvailable($request, $validated['maintenance_type_id'] ?? null);
        $items = $validated['items'] ?? null;
        unset($validated['items']);

        $record->update($validated);

        if ($items !== null) {
            $record->items()->delete();
            $record->items()->createMany($items);
        }

        app(OdometerService::class)->record($record->vehicle, $record->odometer_km);

        return new MaintenanceRecordResource($record->load(['items', 'type']));
    }

    public function destroy(Request $request, MaintenanceRecord $record): JsonResponse
    {
        $this->authorize('delete', $record);

        $record->delete();

        return response()->json(['message' => 'Mantenimiento eliminado.']);
    }

    public function storeAttachment(Request $request, MaintenanceRecord $record): MaintenanceRecordResource
    {
        $this->authorize('update', $record);

        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,webp,pdf|max:10240',
        ]);

        $record->addMediaFromRequest('file')->toMediaCollection('attachments');

        return new MaintenanceRecordResource($record->load(['items', 'type']));
    }

    public function destroyAttachment(Request $request, MaintenanceRecord $record, int $media): MaintenanceRecordResource
    {
        $this->authorize('update', $record);

        $mediaItem = $record->getMedia('attachments')->firstWhere('id', $media);
        abort_unless($mediaItem !== null, 404);

        $mediaItem->delete();

        return new MaintenanceRecordResource($record->fresh(['items', 'type']));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'maintenance_type_id' => 'nullable|integer|exists:maintenance_types,id',
            'performed_on' => 'required|date',
            'odometer_km' => 'required|integer|min:0',
            'provider' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:2000',
            'next_due_km' => 'nullable|integer|min:0',
            'next_due_date' => 'nullable|date',
            'items' => 'array',
            'items.*.kind' => 'required|in:labor,part,other',
            'items.*.description' => 'required|string|max:200',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    private function assertTypeAvailable(Request $request, ?int $typeId): void
    {
        if ($typeId === null) {
            return;
        }

        abort_unless(
            MaintenanceType::availableTo($request->user())->whereKey($typeId)->exists(),
            403,
        );
    }
}

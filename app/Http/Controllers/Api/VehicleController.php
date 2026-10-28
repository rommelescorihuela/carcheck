<?php

namespace App\Http\Controllers\Api;

use App\Enums\FuelType;
use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleResource;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = app(VehicleScope::class)->for($request)->latest();

        return VehicleResource::collection($query->paginate(15))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());
        unset($validated['organization_id']);

        $user = $request->user();

        if ($request->filled('organization_id')) {
            $organization = Organization::findOrFail((int) $request->input('organization_id'));
            abort_unless(
                in_array($organization->roleOf($user), ['owner', 'admin'], true),
                403,
            );
            $owner = $organization;
        } else {
            $owner = $user;
        }

        $vehicle = new Vehicle($validated);
        $vehicle->owner()->associate($owner);
        $vehicle->save();

        return (new VehicleResource($vehicle))->response()->setStatusCode(201);
    }

    public function show(Request $request, Vehicle $vehicle): VehicleResource
    {
        $this->authorize('view', $vehicle);

        return new VehicleResource($vehicle);
    }

    public function update(Request $request, Vehicle $vehicle): VehicleResource
    {
        $this->authorize('update', $vehicle);

        $rules = $this->rules($vehicle);
        unset($rules['organization_id']);

        $vehicle->update($request->validate($rules));

        return new VehicleResource($vehicle);
    }

    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('delete', $vehicle);

        $vehicle->delete();

        return response()->json(['message' => 'Vehículo eliminado.']);
    }

    public function storePhoto(Request $request, Vehicle $vehicle): VehicleResource
    {
        $this->authorize('update', $vehicle);

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,webp|max:5120',
        ]);

        $vehicle->addMediaFromRequest('photo')->toMediaCollection('photo');

        return new VehicleResource($vehicle->fresh());
    }

    public function destroyPhoto(Request $request, Vehicle $vehicle): VehicleResource
    {
        $this->authorize('update', $vehicle);

        $vehicle->clearMediaCollection('photo');

        return new VehicleResource($vehicle->fresh());
    }

    public function transfer(Request $request, Vehicle $vehicle): VehicleResource
    {
        $this->authorize('transfer', $vehicle);

        $validated = $request->validate([
            'owner_type' => 'required|in:user,organization',
            'owner_id' => 'required|integer',
        ]);

        $user = $request->user();

        if ($validated['owner_type'] === 'user') {
            abort_unless(
                (int) $validated['owner_id'] === $user->getKey(),
                403,
                'Solo puedes traspasar a tu propia cuenta.',
            );
            $target = $user;
        } else {
            $organization = Organization::findOrFail((int) $validated['owner_id']);
            abort_unless(
                in_array($organization->roleOf($user), ['owner', 'admin'], true),
                403,
            );
            $target = $organization;
        }

        // Org → personal: solo el `owner` de la organización puede sacarlo.
        $currentOwner = $vehicle->owner;
        if ($currentOwner instanceof Organization && $target instanceof User) {
            abort_unless(
                $currentOwner->roleOf($user) === 'owner',
                403,
                'Solo el propietario de la organización puede sacar el vehículo.',
            );
        }

        $vehicle->owner()->associate($target);
        $vehicle->save();

        return new VehicleResource($vehicle->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?Vehicle $vehicle = null): array
    {
        // En creación `make`/`model` son obligatorios; en edición (PATCH) opcionales.
        $required = $vehicle === null ? 'required' : 'sometimes';

        return [
            'make' => "$required|string|max:100",
            'model' => "$required|string|max:100",
            'year' => 'nullable|integer|between:1900,'.(date('Y') + 1),
            'plate' => 'nullable|string|max:16',
            'vin' => [
                'nullable', 'string', 'max:32',
                Rule::unique('vehicles', 'vin')->ignore($vehicle?->id),
            ],
            'type' => ['sometimes', Rule::enum(VehicleType::class)],
            'fuel_type' => ['sometimes', Rule::enum(FuelType::class)],
            'odometer_km' => 'sometimes|integer|min:0',
            'color' => 'nullable|string|max:40',
            'notes' => 'nullable|string|max:2000',
            'organization_id' => 'sometimes|nullable|integer|exists:organizations,id',
        ];
    }
}

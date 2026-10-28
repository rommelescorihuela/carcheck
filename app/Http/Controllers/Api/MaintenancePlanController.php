<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaintenancePlanResource;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceType;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenancePlanController extends Controller
{
    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        $plans = $vehicle->maintenancePlans()->with('type')->get();

        return MaintenancePlanResource::collection($plans)->response();
    }

    public function update(Request $request, Vehicle $vehicle, MaintenanceType $type): MaintenancePlanResource
    {
        $this->authorize('update', $vehicle);

        abort_unless(
            MaintenanceType::availableTo($request->user())->whereKey($type->id)->exists(),
            403,
        );

        $validated = $request->validate([
            'interval_km' => 'nullable|integer|min:1',
            'interval_months' => 'nullable|integer|min:1',
        ]);

        $plan = MaintenancePlan::updateOrCreate(
            ['vehicle_id' => $vehicle->id, 'maintenance_type_id' => $type->id],
            $validated,
        );

        return new MaintenancePlanResource($plan->load('type'));
    }

    public function destroy(Request $request, Vehicle $vehicle, MaintenanceType $type): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $vehicle->maintenancePlans()
            ->where('maintenance_type_id', $type->id)
            ->delete();

        return response()->json(['message' => 'Plan eliminado.']);
    }
}

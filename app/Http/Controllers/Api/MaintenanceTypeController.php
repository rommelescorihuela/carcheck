<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaintenanceTypeResource;
use App\Models\MaintenanceType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MaintenanceTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $types = MaintenanceType::availableTo($request->user())->orderBy('name')->get();

        return MaintenanceTypeResource::collection($types)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());
        unset($validated['organization_id']);

        $type = new MaintenanceType($validated);
        $type->owner()->associate($this->resolveOwner($request, $request->user()));
        $type->save();

        return (new MaintenanceTypeResource($type))->response()->setStatusCode(201);
    }

    public function update(Request $request, MaintenanceType $type): MaintenanceTypeResource
    {
        $this->authorize('update', $type);

        $rules = $this->rules();
        unset($rules['organization_id']);

        $type->update($request->validate($rules));

        return new MaintenanceTypeResource($type);
    }

    public function destroy(Request $request, MaintenanceType $type): JsonResponse
    {
        $this->authorize('delete', $type);

        if ($type->records()->exists() || $type->plans()->exists()) {
            throw ValidationException::withMessages([
                'type' => ['El tipo está en uso y no se puede eliminar.'],
            ]);
        }

        $type->delete();

        return response()->json(['message' => 'Tipo eliminado.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'interval_km' => 'nullable|integer|min:1',
            'interval_months' => 'nullable|integer|min:1',
            'organization_id' => 'sometimes|nullable|integer|exists:organizations,id',
        ];
    }

    private function resolveOwner(Request $request, User $user): User|Organization
    {
        if ($request->filled('organization_id')) {
            $organization = Organization::findOrFail((int) $request->input('organization_id'));
            abort_unless(
                in_array($organization->roleOf($user), ['owner', 'admin'], true),
                403,
            );

            return $organization;
        }

        return $user;
    }
}

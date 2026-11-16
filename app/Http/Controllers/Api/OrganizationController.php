<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizations = $request->user()
            ->organizations()
            ->orderBy('name')
            ->get();

        return response()->json(['organizations' => $organizations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'currency' => 'sometimes|string|size:3',
            'timezone' => 'sometimes|string|max:64',
        ]);

        // El modelo agrega al propietario como miembro (`created`).
        $organization = $request->user()->ownedOrganizations()->create($validated);

        return response()->json(['organization' => $organization], 201);
    }

    public function show(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return response()->json([
            'organization' => $organization->loadCount('members'),
        ]);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'currency' => 'sometimes|string|size:3',
            'timezone' => 'sometimes|string|max:64',
        ]);

        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        $organization->update($validated);

        return response()->json(['organization' => $organization->refresh()->loadCount('members')]);
    }

    /**
     * Dar de baja la organización. El modelo hace soft delete de sus vehículos y
     * conserva las suscripciones como historial de facturación.
     */
    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('delete', $organization);

        DB::transaction(function () use ($organization): void {
            // Se conserva el historial de facturación, pero la suscripción deja de estar activa.
            $organization->subscriptions()->get()->each(function ($subscription): void {
                $subscription->update([
                    'status' => SubscriptionStatus::Canceled,
                    'canceled_at' => now(),
                ]);
            });

            $organization->delete();
        });

        return response()->json(['message' => 'Organización eliminada.']);
    }

    /**
     * Transfiere la propiedad a otro miembro. El propietario anterior baja a admin.
     */
    public function transfer(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('delete', $organization);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $target = User::findOrFail($validated['user_id']);

        if (! $organization->hasMember($target)) {
            throw ValidationException::withMessages([
                'user_id' => ['El nuevo propietario debe ser miembro de la organización.'],
            ]);
        }

        if ((int) $target->id === (int) $organization->owner_id) {
            throw ValidationException::withMessages([
                'user_id' => ['Ese usuario ya es el propietario.'],
            ]);
        }

        DB::transaction(function () use ($organization, $target): void {
            $organization->update(['owner_id' => $target->id]);
        });

        return response()->json([
            'organization' => $organization->refresh()->loadCount('members'),
        ]);
    }

    /**
     * Salir de una organización. El propietario debe transferirla o eliminarla.
     */
    public function leave(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        if ((int) $organization->owner_id === (int) $request->user()->id) {
            throw ValidationException::withMessages([
                'organization' => [
                    'El propietario no puede salir: transfiere la propiedad o elimina la organización.',
                ],
            ]);
        }

        $organization->members()->detach($request->user()->id);

        return response()->json(['message' => 'Saliste de la organización.']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}

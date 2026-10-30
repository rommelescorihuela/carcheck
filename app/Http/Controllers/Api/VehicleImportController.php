<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\VehicleImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleImportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
            'organization_id' => 'nullable|integer|exists:organizations,id',
        ]);

        $user = $request->user();

        if ($request->filled('organization_id')) {
            $organization = Organization::findOrFail((int) $request->input('organization_id'));
            abort_unless(
                $organization->isManager($user),
                403,
                'Solo el propietario o un administrador pueden importar vehículos.',
            );
            $owner = $organization;
        } else {
            $owner = $user;
        }

        $result = app(VehicleImport::class)->import($request->file('file'), $user, $owner);

        return response()->json(['data' => $result]);
    }
}

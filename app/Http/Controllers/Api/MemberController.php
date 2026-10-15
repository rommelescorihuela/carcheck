<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MemberController extends Controller
{
    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        $members = $organization->members()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'joined_at' => $user->pivot->joined_at,
            ]);

        return response()->json(['members' => $members]);
    }

    public function update(Request $request, Organization $organization, User $user): JsonResponse
    {
        $this->authorize('changeRole', $organization);

        $validated = $request->validate([
            'role' => 'required|in:admin,member',
        ]);

        if (! $organization->hasMember($user)) {
            abort(404, 'El usuario no pertenece a la organización.');
        }

        if ((int) $organization->owner_id === $user->id) {
            throw ValidationException::withMessages([
                'role' => ['No se puede cambiar el rol del propietario.'],
            ]);
        }

        $organization->members()->updateExistingPivot($user->id, ['role' => $validated['role']]);

        return response()->json(['message' => 'Rol actualizado.']);
    }

    public function destroy(Request $request, Organization $organization, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $organization);

        if (! $organization->hasMember($user)) {
            abort(404, 'El usuario no pertenece a la organización.');
        }

        if ((int) $organization->owner_id === $user->id) {
            throw ValidationException::withMessages([
                'user' => ['No se puede quitar al propietario de la organización.'],
            ]);
        }

        $organization->members()->detach($user->id);

        return response()->json(['message' => 'Miembro eliminado.']);
    }
}

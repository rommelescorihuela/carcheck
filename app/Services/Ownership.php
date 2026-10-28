<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resuelve el propietario de una petición: la organización indicada (`organization_id`) o el propio
 * usuario (ámbito personal). Centraliza la regla para que `billing` no la reimplemente.
 */
class Ownership
{
    public function resolve(Request $request): User|Organization
    {
        $user = $request->user();

        if ($request->filled('organization_id')) {
            $organization = Organization::findOrFail((int) $request->input('organization_id'));

            abort_unless($organization->hasMember($user), 403);

            return $organization;
        }

        return $user;
    }

    public function canManage(User $user, User|Organization $owner): bool
    {
        if ($owner instanceof User) {
            return $owner->is($user);
        }

        return in_array($owner->roleOf($user), ['owner', 'admin'], true);
    }
}

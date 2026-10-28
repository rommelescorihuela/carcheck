<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Resuelve el ámbito de vehículos de una petición: una organización concreta (`organization_id`,
 * debe ser miembro), el ámbito personal (`scope=personal`) o todo lo accesible por el usuario.
 * Es la única implementación: `vehicles`, `reminders` y `reports` la comparten.
 */
class VehicleScope
{
    /**
     * @return Builder<Vehicle>
     */
    public function for(Request $request): Builder
    {
        $user = $request->user();
        $query = Vehicle::query();

        if ($request->filled('organization_id')) {
            $organization = Organization::findOrFail((int) $request->input('organization_id'));
            abort_unless($organization->hasMember($user), 403);

            return $query->where('owner_type', $organization->getMorphClass())
                ->where('owner_id', $organization->getKey());
        }

        if ($request->input('scope') === 'personal') {
            return $query->where('owner_type', $user->getMorphClass())
                ->where('owner_id', $user->getKey());
        }

        return $query->accessibleBy($user);
    }

    /**
     * Propietarios cuyo gasto de suscripción entra en el ámbito de la petición.
     *
     * @return array<int, User|Organization>
     */
    public function owners(Request $request): array
    {
        $user = $request->user();

        if ($request->filled('organization_id')) {
            return [Organization::findOrFail((int) $request->input('organization_id'))];
        }

        if ($request->input('scope') === 'personal') {
            return [$user];
        }

        return [$user, ...$user->organizations()->get()->all()];
    }
}

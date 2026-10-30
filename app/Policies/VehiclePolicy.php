<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function view(User $user, Vehicle $vehicle): bool
    {
        return $vehicle->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->manage($user, $vehicle);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->manage($user, $vehicle);
    }

    public function transfer(User $user, Vehicle $vehicle): bool
    {
        return $this->manage($user, $vehicle);
    }

    /**
     * Registrar combustible, mantenimiento o kilometraje. En una flota lo hace el `driver`, que
     * no puede administrar el garaje ni la facturación.
     */
    public function log(User $user, Vehicle $vehicle): bool
    {
        if ($vehicle->owner_type === $user->getMorphClass()) {
            return (int) $vehicle->owner_id === $user->getKey();
        }

        if ($vehicle->owner_type === (new Organization)->getMorphClass()) {
            /** @var Organization $organization */
            $organization = $vehicle->owner;

            return $organization->canLog($user);
        }

        return false;
    }

    private function manage(User $user, Vehicle $vehicle): bool
    {
        if ($vehicle->owner_type === $user->getMorphClass()) {
            return (int) $vehicle->owner_id === $user->getKey();
        }

        if ($vehicle->owner_type === (new Organization)->getMorphClass()) {
            /** @var Organization $organization */
            $organization = $vehicle->owner;

            return $organization->isManager($user);
        }

        return false;
    }
}

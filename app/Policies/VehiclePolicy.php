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

    private function manage(User $user, Vehicle $vehicle): bool
    {
        if ($vehicle->owner_type === $user->getMorphClass()) {
            return (int) $vehicle->owner_id === $user->getKey();
        }

        if ($vehicle->owner_type === (new Organization)->getMorphClass()) {
            /** @var Organization $organization */
            $organization = $vehicle->owner;

            return in_array($organization->roleOf($user), ['owner', 'admin'], true);
        }

        return false;
    }
}

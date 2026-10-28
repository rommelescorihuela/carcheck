<?php

namespace App\Policies;

use App\Models\FuelEntry;
use App\Models\User;
use App\Models\Vehicle;

class FuelEntryPolicy
{
    /**
     * La autorización se delega en el vehículo dueño de la carga.
     */
    public function view(User $user, FuelEntry $entry): bool
    {
        return (new VehiclePolicy)->view($user, $this->vehicleOf($entry));
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FuelEntry $entry): bool
    {
        return (new VehiclePolicy)->update($user, $this->vehicleOf($entry));
    }

    public function delete(User $user, FuelEntry $entry): bool
    {
        return (new VehiclePolicy)->delete($user, $this->vehicleOf($entry));
    }

    private function vehicleOf(FuelEntry $entry): Vehicle
    {
        /** @var Vehicle $vehicle */
        $vehicle = $entry->vehicle;

        return $vehicle;
    }
}

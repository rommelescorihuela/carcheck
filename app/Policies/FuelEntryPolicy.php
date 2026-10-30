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

    /**
     * Quien registró la carga puede corregirla; el resto necesita administrar el vehículo.
     */
    public function update(User $user, FuelEntry $entry): bool
    {
        return $this->modify($user, $entry);
    }

    public function delete(User $user, FuelEntry $entry): bool
    {
        return $this->modify($user, $entry);
    }

    private function modify(User $user, FuelEntry $entry): bool
    {
        $vehicle = $this->vehicleOf($entry);

        if ($entry->user_id !== null
            && (int) $entry->user_id === $user->getKey()
            && (new VehiclePolicy)->log($user, $vehicle)) {
            return true;
        }

        return (new VehiclePolicy)->update($user, $vehicle);
    }

    private function vehicleOf(FuelEntry $entry): Vehicle
    {
        /** @var Vehicle $vehicle */
        $vehicle = $entry->vehicle;

        return $vehicle;
    }
}

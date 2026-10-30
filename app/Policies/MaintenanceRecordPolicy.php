<?php

namespace App\Policies;

use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;

class MaintenanceRecordPolicy
{
    /**
     * La autorización se delega en el vehículo dueño del registro.
     */
    public function view(User $user, MaintenanceRecord $record): bool
    {
        return (new VehiclePolicy)->view($user, $this->vehicleOf($record));
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Quien registró el servicio puede corregirlo; el resto necesita administrar el vehículo.
     */
    public function update(User $user, MaintenanceRecord $record): bool
    {
        return $this->modify($user, $record);
    }

    public function delete(User $user, MaintenanceRecord $record): bool
    {
        return $this->modify($user, $record);
    }

    private function modify(User $user, MaintenanceRecord $record): bool
    {
        $vehicle = $this->vehicleOf($record);

        if ($record->user_id !== null
            && (int) $record->user_id === $user->getKey()
            && (new VehiclePolicy)->log($user, $vehicle)) {
            return true;
        }

        return (new VehiclePolicy)->update($user, $vehicle);
    }

    private function vehicleOf(MaintenanceRecord $record): Vehicle
    {
        /** @var Vehicle $vehicle */
        $vehicle = $record->vehicle;

        return $vehicle;
    }
}

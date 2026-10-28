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

    public function update(User $user, MaintenanceRecord $record): bool
    {
        return (new VehiclePolicy)->update($user, $this->vehicleOf($record));
    }

    public function delete(User $user, MaintenanceRecord $record): bool
    {
        return (new VehiclePolicy)->delete($user, $this->vehicleOf($record));
    }

    private function vehicleOf(MaintenanceRecord $record): Vehicle
    {
        /** @var Vehicle $vehicle */
        $vehicle = $record->vehicle;

        return $vehicle;
    }
}

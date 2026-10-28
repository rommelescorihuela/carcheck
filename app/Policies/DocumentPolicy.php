<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Models\Vehicle;

class DocumentPolicy
{
    /**
     * La autorización se delega en el vehículo dueño del documento.
     */
    public function view(User $user, Document $document): bool
    {
        return (new VehiclePolicy)->view($user, $this->vehicleOf($document));
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Document $document): bool
    {
        return (new VehiclePolicy)->update($user, $this->vehicleOf($document));
    }

    public function delete(User $user, Document $document): bool
    {
        return (new VehiclePolicy)->delete($user, $this->vehicleOf($document));
    }

    private function vehicleOf(Document $document): Vehicle
    {
        /** @var Vehicle $vehicle */
        $vehicle = $document->vehicle;

        return $vehicle;
    }
}

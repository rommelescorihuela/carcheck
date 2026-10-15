<?php

namespace App\Services;

use App\Models\Vehicle;

class OdometerService
{
    /**
     * Sube el odómetro del vehículo solo si el nuevo valor es mayor.
     * Nunca lo baja (lo usan `maintenance` y `fuel` al registrar con odómetro).
     */
    public function record(Vehicle $vehicle, int $km): void
    {
        if ($km > $vehicle->odometer_km) {
            $vehicle->update(['odometer_km' => $km]);
        }
    }
}

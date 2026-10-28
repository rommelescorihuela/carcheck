<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use App\Models\MaintenanceType;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenancePlan>
 */
class MaintenancePlanFactory extends Factory
{
    protected $model = MaintenancePlan::class;

    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'maintenance_type_id' => MaintenanceType::factory(),
            'interval_km' => 10000,
            'interval_months' => 12,
        ];
    }
}

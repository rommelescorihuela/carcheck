<?php

namespace Database\Factories;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRecord>
 */
class MaintenanceRecordFactory extends Factory
{
    protected $model = MaintenanceRecord::class;

    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'maintenance_type_id' => MaintenanceType::factory(),
            'performed_on' => now()->subDays($this->faker->numberBetween(1, 300))->toDateString(),
            'odometer_km' => $this->faker->numberBetween(1000, 200000),
            'provider' => $this->faker->company(),
            'notes' => null,
            'next_due_km' => null,
            'next_due_date' => null,
            'currency' => 'USD',
        ];
    }
}

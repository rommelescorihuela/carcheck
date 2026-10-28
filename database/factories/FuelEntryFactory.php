<?php

namespace Database\Factories;

use App\Models\FuelEntry;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelEntry>
 */
class FuelEntryFactory extends Factory
{
    protected $model = FuelEntry::class;

    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'filled_on' => now()->subDays($this->faker->numberBetween(1, 120))->toDateString(),
            'odometer_km' => $this->faker->numberBetween(1000, 200000),
            'liters' => $this->faker->randomFloat(2, 10, 60),
            'unit_price' => $this->faker->randomFloat(3, 0.1, 2),
            'full_tank' => true,
            'station' => $this->faker->company(),
            'notes' => null,
            'currency' => 'USD',
        ];
    }
}

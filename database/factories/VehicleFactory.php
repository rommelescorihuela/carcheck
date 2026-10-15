<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Enums\VehicleType;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'owner_type' => User::class,
            'owner_id' => User::factory(),
            'make' => $this->faker->randomElement(['Toyota', 'Ford', 'Chevrolet', 'Hyundai', 'Kia']),
            'model' => ucfirst($this->faker->word()),
            'year' => $this->faker->numberBetween(2005, (int) date('Y') + 1),
            'plate' => strtoupper($this->faker->bothify('???-###')),
            'vin' => strtoupper($this->faker->unique()->bothify('?????????????????')),
            'type' => VehicleType::Car,
            'fuel_type' => FuelType::Gasoline,
            'odometer_km' => $this->faker->numberBetween(0, 200000),
            'color' => $this->faker->safeColorName(),
            'notes' => null,
        ];
    }

    public function forUser(?User $user = null): static
    {
        return $this->state(fn () => [
            'owner_type' => User::class,
            'owner_id' => $user?->id ?? User::factory(),
        ]);
    }

    public function forOrganization(?Organization $organization = null): static
    {
        return $this->state(fn () => [
            'owner_type' => Organization::class,
            'owner_id' => $organization?->id ?? Organization::factory(),
        ]);
    }
}

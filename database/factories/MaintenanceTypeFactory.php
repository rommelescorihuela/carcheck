<?php

namespace Database\Factories;

use App\Models\MaintenanceType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceType>
 */
class MaintenanceTypeFactory extends Factory
{
    protected $model = MaintenanceType::class;

    public function definition(): array
    {
        return [
            'owner_type' => null,
            'owner_id' => null,
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'slug' => null,
            'interval_km' => $this->faker->randomElement([null, 5000, 10000]),
            'interval_months' => $this->faker->randomElement([null, 6, 12]),
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

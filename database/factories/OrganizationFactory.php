<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => 'Taller '.$this->faker->unique()->company(),
            'owner_id' => User::factory(),
            'currency' => 'USD',
            'timezone' => 'America/Caracas',
        ];
    }
}

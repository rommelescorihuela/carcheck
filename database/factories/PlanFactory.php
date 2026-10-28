<?php

namespace Database\Factories;

use App\Enums\PlanInterval;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $code = Str::lower($this->faker->unique()->bothify('plan-####'));

        return [
            'code' => $code,
            'name' => 'Plan '.Str::upper($code),
            'description' => $this->faker->sentence(6),
            'price' => $this->faker->randomFloat(2, 3, 25),
            'currency' => 'USD',
            'interval' => PlanInterval::Monthly,
            'trial_days' => 14,
            'max_vehicles' => 3,
            'max_members' => 3,
            'is_active' => true,
            'sort' => 0,
        ];
    }

    public function yearly(): static
    {
        return $this->state(fn (): array => ['interval' => PlanInterval::Yearly]);
    }

    public function withoutTrial(): static
    {
        return $this->state(fn (): array => ['trial_days' => 0]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}

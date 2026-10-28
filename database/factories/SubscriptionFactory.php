<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'owner_type' => (new User)->getMorphClass(),
            'owner_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'canceled_at' => null,
            'payment_method' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
        ]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => [
            'owner_type' => $organization->getMorphClass(),
            'owner_id' => $organization->getKey(),
        ]);
    }

    public function trialing(?int $days = 14): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays($days ?? 14),
            'current_period_start' => null,
            'current_period_end' => null,
        ]);
    }

    public function pendingPayment(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::PendingPayment,
            'trial_ends_at' => null,
            'current_period_start' => null,
            'current_period_end' => null,
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subMonth()->toDateString(),
            'current_period_end' => now()->subDay()->toDateString(),
        ]);
    }
}

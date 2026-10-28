<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'amount' => 10,
            'currency' => 'USD',
            'exchange_rate' => 1,
            'method' => PaymentMethod::Transfer,
            'reference' => strtoupper($this->faker->bothify('REF-####??')),
            'paid_on' => now()->toDateString(),
            'status' => PaymentStatus::Pending,
            'notes' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Rejected,
            'reviewed_at' => now(),
        ]);
    }
}

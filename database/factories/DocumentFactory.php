<?php

namespace Database\Factories;

use App\Enums\DocumentKind;
use App\Models\Document;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'kind' => DocumentKind::Insurance,
            'name' => 'Seguro '.$this->faker->year(),
            'issuer' => $this->faker->company(),
            'reference' => strtoupper($this->faker->bothify('POL-####??')),
            'issued_on' => now()->subYear()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
            'notes' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_on' => now()->subDays(10)->toDateString(),
        ]);
    }

    public function expiring(): static
    {
        return $this->state(fn (): array => [
            'expires_on' => now()->addDays(10)->toDateString(),
        ]);
    }

    public function withoutExpiry(): static
    {
        return $this->state(fn (): array => [
            'issued_on' => now()->subYear()->toDateString(),
            'expires_on' => null,
        ]);
    }

    public function ofKind(DocumentKind $kind): static
    {
        return $this->state(fn (): array => ['kind' => $kind]);
    }
}

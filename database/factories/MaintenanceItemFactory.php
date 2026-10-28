<?php

namespace Database\Factories;

use App\Enums\MaintenanceItemKind;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceItem>
 */
class MaintenanceItemFactory extends Factory
{
    protected $model = MaintenanceItem::class;

    public function definition(): array
    {
        return [
            'maintenance_record_id' => MaintenanceRecord::factory(),
            'kind' => MaintenanceItemKind::Part,
            'description' => ucfirst($this->faker->words(2, true)),
            'quantity' => 1,
            'unit_price' => $this->faker->randomFloat(2, 5, 500),
        ];
    }
}

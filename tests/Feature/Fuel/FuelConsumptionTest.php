<?php

namespace Tests\Feature\Fuel;

use App\Models\FuelEntry;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\FuelConsumption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelConsumptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_full_tanks_produce_distance_and_consumption(): void
    {
        $vehicle = $this->vehicleWithEntries([
            ['odometer_km' => 1000, 'liters' => 40, 'unit_price' => 1],
            ['odometer_km' => 1400, 'liters' => 35, 'unit_price' => 1],
        ]);

        $rows = app(FuelConsumption::class)->forVehicle($vehicle);

        $this->assertNull($rows[0]['distance_km']);
        $this->assertNull($rows[0]['km_per_liter']);

        $this->assertSame(400, $rows[1]['distance_km']);
        $this->assertSame(35.0, $rows[1]['liters_since_full']);
        $this->assertSame(11.43, $rows[1]['km_per_liter']);
        $this->assertSame(0.0875, $rows[1]['cost_per_km']);
    }

    public function test_partial_fill_accumulates_liters_until_the_next_full_tank(): void
    {
        $vehicle = $this->vehicleWithEntries([
            ['odometer_km' => 1000, 'liters' => 40, 'unit_price' => 1],
            ['odometer_km' => 1200, 'liters' => 20, 'unit_price' => 1, 'full_tank' => false],
            ['odometer_km' => 1400, 'liters' => 35, 'unit_price' => 1],
        ]);

        $rows = app(FuelConsumption::class)->forVehicle($vehicle);

        // La parcial informa distancia pero no rendimiento.
        $this->assertSame(200, $rows[1]['distance_km']);
        $this->assertNull($rows[1]['liters_since_full']);
        $this->assertNull($rows[1]['km_per_liter']);

        // La que cierra el tanque mide desde el último tanque lleno: 400 km con 55 L.
        $this->assertSame(400, $rows[2]['consumption_distance_km']);
        $this->assertSame(55.0, $rows[2]['liters_since_full']);
        $this->assertSame(7.27, $rows[2]['km_per_liter']);
    }

    public function test_same_odometer_does_not_divide_by_zero(): void
    {
        $vehicle = $this->vehicleWithEntries([
            ['odometer_km' => 1000, 'liters' => 40, 'unit_price' => 1],
            ['odometer_km' => 1000, 'liters' => 40, 'unit_price' => 1],
        ]);

        $rows = app(FuelConsumption::class)->forVehicle($vehicle);

        $this->assertSame(0, $rows[1]['distance_km']);
        $this->assertNull($rows[1]['km_per_liter']);
        $this->assertNull($rows[1]['cost_per_km']);
    }

    public function test_summary_aggregates_all_entries(): void
    {
        $vehicle = $this->vehicleWithEntries([
            ['odometer_km' => 1000, 'liters' => 40, 'unit_price' => 1],
            ['odometer_km' => 1200, 'liters' => 20, 'unit_price' => 1, 'full_tank' => false],
            ['odometer_km' => 1400, 'liters' => 35, 'unit_price' => 1],
        ]);

        $summary = app(FuelConsumption::class)->summary($vehicle);

        $this->assertSame(3, $summary['entries']);
        $this->assertSame(95.0, $summary['total_liters']);
        $this->assertSame(95.0, $summary['total_cost']);
        $this->assertSame(400, $summary['distance_km']);
        $this->assertSame(7.27, $summary['km_per_liter']);
        $this->assertSame(0.2375, $summary['cost_per_km']);
    }

    public function test_summary_of_a_vehicle_without_entries_is_empty(): void
    {
        $user = User::factory()->create(['currency' => 'VES']);
        $vehicle = Vehicle::factory()->forUser($user)->create();

        $summary = app(FuelConsumption::class)->summary($vehicle);

        $this->assertSame(0, $summary['entries']);
        $this->assertSame(0.0, $summary['total_liters']);
        $this->assertNull($summary['km_per_liter']);
        $this->assertNull($summary['cost_per_km']);
        $this->assertSame('VES', $summary['currency']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function vehicleWithEntries(array $entries): Vehicle
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        foreach ($entries as $index => $entry) {
            FuelEntry::factory()->for($vehicle)->create($entry + [
                'filled_on' => now()->addDays($index)->toDateString(),
                'currency' => 'USD',
            ]);
        }

        return $vehicle;
    }
}

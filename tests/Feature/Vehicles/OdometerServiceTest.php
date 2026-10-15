<?php

namespace Tests\Feature\Vehicles;

use App\Models\Vehicle;
use App\Services\OdometerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OdometerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_raises_the_odometer(): void
    {
        $vehicle = Vehicle::factory()->create(['odometer_km' => 1000]);

        app(OdometerService::class)->record($vehicle, 1500);

        $this->assertSame(1500, $vehicle->fresh()->odometer_km);
    }

    public function test_it_does_not_lower_the_odometer(): void
    {
        $vehicle = Vehicle::factory()->create(['odometer_km' => 1000]);

        app(OdometerService::class)->record($vehicle, 500);

        $this->assertSame(1000, $vehicle->fresh()->odometer_km);
    }

    public function test_equal_value_is_a_noop(): void
    {
        $vehicle = Vehicle::factory()->create(['odometer_km' => 1000]);

        app(OdometerService::class)->record($vehicle, 1000);

        $this->assertSame(1000, $vehicle->fresh()->odometer_km);
    }
}

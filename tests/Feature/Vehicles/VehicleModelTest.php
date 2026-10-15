<?php

namespace Tests\Feature\Vehicles;

use App\Enums\FuelType;
use App\Enums\VehicleType;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_personal_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->assertInstanceOf(User::class, $vehicle->owner);
        $this->assertSame(VehicleType::Car, $vehicle->type);
        $this->assertSame(FuelType::Gasoline, $vehicle->fuel_type);
        $this->assertSame(0, $vehicle->media()->count());
    }

    public function test_factory_can_create_organization_vehicle(): void
    {
        $organization = Organization::factory()->create();
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        $this->assertTrue($vehicle->owner->is($organization));
    }

    public function test_vehicle_is_soft_deleted(): void
    {
        $vehicle = Vehicle::factory()->create();
        $vehicle->delete();

        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_photo_collection_is_single_file(): void
    {
        $vehicle = Vehicle::factory()->create();

        $collection = $vehicle->getMediaCollection('photo');

        $this->assertNotNull($collection);
        $this->assertTrue($collection->singleFile);
        $this->assertNull($vehicle->photo_url);
    }
}

<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceItem;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\MaintenanceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_total_sums_items(): void
    {
        $record = MaintenanceRecord::factory()->create();
        MaintenanceItem::factory()->for($record, 'record')->create(['quantity' => 2, 'unit_price' => 10]);
        MaintenanceItem::factory()->for($record, 'record')->create(['quantity' => 1, 'unit_price' => 25.50]);

        $this->assertEquals(45.50, $record->fresh()->total);
    }

    public function test_record_attachments_collection_is_multiple(): void
    {
        $record = MaintenanceRecord::factory()->create();

        $collection = $record->getMediaCollection('attachments');

        $this->assertNotNull($collection);
        $this->assertFalse($collection->singleFile);
    }

    public function test_global_type_and_available_scope(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->attach($user, ['role' => 'member', 'joined_at' => now()]);

        $global = MaintenanceType::factory()->create();
        $own = MaintenanceType::factory()->forUser($user)->create();
        $orgType = MaintenanceType::factory()->forOrganization($organization)->create();
        $foreign = MaintenanceType::factory()->forUser(User::factory()->create())->create();

        $this->assertTrue($global->isGlobal());

        $ids = MaintenanceType::availableTo($user)->pluck('id');

        $this->assertTrue($ids->contains($global->id));
        $this->assertTrue($ids->contains($own->id));
        $this->assertTrue($ids->contains($orgType->id));
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_vehicle_has_maintenance_records(): void
    {
        $vehicle = Vehicle::factory()->create();
        MaintenanceRecord::factory()->for($vehicle)->create();

        $this->assertSame(1, $vehicle->maintenanceRecords()->count());
    }

    public function test_seeder_creates_global_types(): void
    {
        $this->seed(MaintenanceTypeSeeder::class);

        $this->assertGreaterThan(0, MaintenanceType::whereNull('owner_type')->count());
        $this->assertDatabaseHas('maintenance_types', [
            'name' => 'Cambio de aceite',
            'owner_type' => null,
        ]);
    }
}

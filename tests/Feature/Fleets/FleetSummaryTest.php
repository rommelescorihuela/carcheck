<?php

namespace Tests\Feature\Fleets;

use App\Models\Document;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_consolidates_spend_documents_and_due_maintenance(): void
    {
        [$organization, $owner, $vehicle, $other] = $this->fleet();

        $vehicle->fuelEntries()->create([
            'filled_on' => now()->subDay()->toDateString(),
            'odometer_km' => 1000,
            'liters' => 40,
            'unit_price' => 1.5,
            'currency' => 'USD',
        ]);

        $type = MaintenanceType::factory()->create(['owner_type' => null, 'owner_id' => null]);
        MaintenancePlan::factory()->create([
            'vehicle_id' => $vehicle->id,
            'maintenance_type_id' => $type->id,
            'interval_km' => 5000,
        ]);

        $record = MaintenanceRecord::factory()->create([
            'vehicle_id' => $vehicle->id,
            'maintenance_type_id' => $type->id,
            'performed_on' => now()->subDay()->toDateString(),
            'odometer_km' => 9000,
            'next_due_km' => 9100,
            'currency' => 'USD',
        ]);
        $record->items()->create([
            'kind' => 'part',
            'description' => 'Aceite',
            'quantity' => 4,
            'unit_price' => 10,
        ]);

        Document::factory()->expired()->create(['vehicle_id' => $vehicle->id]);

        // Carga fuera del periodo: no debe sumar al consolidado.
        $other->fuelEntries()->create([
            'filled_on' => now()->subMonths(2)->toDateString(),
            'odometer_km' => 500,
            'liters' => 10,
            'unit_price' => 2,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/fleet/summary?organization_id={$organization->id}")
            ->assertOk()
            ->assertJsonPath('data.vehicles.total', 2)
            ->assertJsonPath('data.vehicles.with_expired_documents', 1)
            ->assertJsonPath('data.vehicles.with_due_maintenance', 1)
            ->assertJsonPath('data.documents.expired', 1)
            ->assertJsonPath('data.fuel.entries', 1)
            ->assertJsonPath('data.maintenance.records', 1)
            ->assertJsonCount(2, 'data.rows');
    }

    public function test_summary_rejects_a_non_member(): void
    {
        [$organization] = $this->fleet();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/fleet/summary?organization_id={$organization->id}")
            ->assertForbidden();
    }

    public function test_driver_can_read_the_consolidated_view(): void
    {
        [$organization] = $this->fleet();

        $driver = User::factory()->create();
        $organization->members()->attach($driver->id, ['role' => 'driver', 'joined_at' => now()]);

        Sanctum::actingAs($driver);

        $this->getJson("/api/fleet/summary?organization_id={$organization->id}")
            ->assertOk()
            ->assertJsonPath('data.vehicles.total', 2);
    }

    /**
     * @return array{0: Organization, 1: User, 2: Vehicle, 3: Vehicle}
     */
    private function fleet(): array
    {
        $organization = Organization::factory()->create();
        $vehicle = Vehicle::factory()->forOrganization($organization)->create(['odometer_km' => 10_000]);
        $other = Vehicle::factory()->forOrganization($organization)->create();

        return [$organization, $organization->owner, $vehicle, $other];
    }
}

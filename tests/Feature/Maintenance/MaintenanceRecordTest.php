<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_record_with_items(): void
    {
        $user = User::factory()->create(['currency' => 'VES']);
        $vehicle = Vehicle::factory()->forUser($user)->create(['odometer_km' => 1000]);
        $type = MaintenanceType::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/maintenance-records", [
            'maintenance_type_id' => $type->id,
            'performed_on' => '2026-01-15',
            'odometer_km' => 12000,
            'provider' => 'Taller Central',
            'items' => [
                ['kind' => 'part', 'description' => 'Aceite 5W30', 'quantity' => 4, 'unit_price' => 10],
                ['kind' => 'labor', 'description' => 'Mano de obra', 'quantity' => 1, 'unit_price' => 25.5],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.currency', 'VES')
            ->assertJsonPath('data.total', 65.5)
            ->assertJsonCount(2, 'data.items');

        // El odómetro del vehículo sube al del registro.
        $this->assertSame(12000, $vehicle->fresh()->odometer_km);
    }

    public function test_odometer_does_not_lower(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create(['odometer_km' => 50000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/maintenance-records", [
            'performed_on' => '2026-01-15',
            'odometer_km' => 40000,
        ])->assertCreated();

        $this->assertSame(50000, $vehicle->fresh()->odometer_km);
    }

    public function test_organization_record_uses_org_currency(): void
    {
        $organization = Organization::factory()->create(['currency' => 'EUR']);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs($organization->owner);

        $this->postJson("/api/vehicles/{$vehicle->id}/maintenance-records", [
            'performed_on' => '2026-02-01',
            'odometer_km' => 100,
        ])->assertCreated()->assertJsonPath('data.currency', 'EUR');
    }

    public function test_plain_member_can_create_record_with_attribution(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs($member);

        $this->postJson("/api/vehicles/{$vehicle->id}/maintenance-records", [
            'performed_on' => '2026-01-15',
            'odometer_km' => 100,
        ])
            ->assertCreated()
            ->assertJsonPath('data.recorded_by', $member->name);

        $this->assertDatabaseHas('maintenance_records', [
            'vehicle_id' => $vehicle->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_outsider_cannot_create_record(): void
    {
        $organization = Organization::factory()->create();
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/vehicles/{$vehicle->id}/maintenance-records", [
            'performed_on' => '2026-01-15',
            'odometer_km' => 100,
        ])->assertForbidden();
    }

    public function test_index_lists_records_of_accessible_vehicle(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        MaintenanceRecord::factory()->for($vehicle)->create();
        MaintenanceRecord::factory()->for($vehicle)->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/vehicles/{$vehicle->id}/maintenance-records")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_foreign_record_is_forbidden(): void
    {
        $user = User::factory()->create();
        $record = MaintenanceRecord::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/maintenance-records/{$record->id}")->assertForbidden();
    }

    public function test_update_replaces_items_and_recomputes_total(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $record = MaintenanceRecord::factory()->for($vehicle)->create();
        $record->items()->create(['kind' => 'part', 'description' => 'Viejo', 'quantity' => 1, 'unit_price' => 10]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/maintenance-records/{$record->id}", [
            'performed_on' => $record->performed_on->toDateString(),
            'odometer_km' => $record->odometer_km,
            'items' => [
                ['kind' => 'part', 'description' => 'Nuevo', 'quantity' => 2, 'unit_price' => 30],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.total', 60)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_foreign_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $foreignType = MaintenanceType::factory()->forUser(User::factory()->create())->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/maintenance-records", [
            'maintenance_type_id' => $foreignType->id,
            'performed_on' => '2026-01-15',
            'odometer_km' => 100,
        ])->assertForbidden();
    }

    public function test_owner_can_delete_record(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $record = MaintenanceRecord::factory()->for($vehicle)->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/maintenance-records/{$record->id}")->assertOk();
        $this->assertDatabaseMissing('maintenance_records', ['id' => $record->id]);
    }
}

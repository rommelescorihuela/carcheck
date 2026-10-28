<?php

namespace Tests\Feature\Fuel;

use App\Models\FuelEntry;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FuelEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_entry_and_total_is_derived(): void
    {
        $user = User::factory()->create(['currency' => 'VES']);
        $vehicle = Vehicle::factory()->forUser($user)->create(['odometer_km' => 1000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/fuel-entries", [
            'filled_on' => '2026-01-15',
            'odometer_km' => 1200,
            'liters' => 40.5,
            'unit_price' => 0.55,
            'full_tank' => true,
            'station' => 'Estación Central',
        ])
            ->assertCreated()
            ->assertJsonPath('data.currency', 'VES')
            ->assertJsonPath('data.total', 22.28)
            ->assertJsonPath('data.full_tank', true);

        // El odómetro del vehículo sube al de la carga.
        $this->assertSame(1200, $vehicle->fresh()->odometer_km);
    }

    public function test_odometer_does_not_lower(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create(['odometer_km' => 50000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/fuel-entries", [
            'filled_on' => '2026-01-15',
            'odometer_km' => 40000,
            'liters' => 30,
            'unit_price' => 1,
        ])->assertCreated();

        $this->assertSame(50000, $vehicle->fresh()->odometer_km);
    }

    public function test_organization_entry_uses_org_currency(): void
    {
        $organization = Organization::factory()->create(['currency' => 'EUR']);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs($organization->owner);

        $this->postJson("/api/vehicles/{$vehicle->id}/fuel-entries", [
            'filled_on' => '2026-02-01',
            'odometer_km' => 100,
            'liters' => 10,
            'unit_price' => 1.5,
        ])->assertCreated()->assertJsonPath('data.currency', 'EUR');
    }

    public function test_plain_member_cannot_create_entry(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs($member);

        $this->postJson("/api/vehicles/{$vehicle->id}/fuel-entries", [
            'filled_on' => '2026-01-15',
            'odometer_km' => 100,
            'liters' => 10,
            'unit_price' => 1,
        ])->assertForbidden();
    }

    public function test_index_lists_entries_with_consumption_metrics(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        FuelEntry::factory()->for($vehicle)->create([
            'filled_on' => '2026-01-01', 'odometer_km' => 1000, 'liters' => 40, 'unit_price' => 1,
        ]);
        FuelEntry::factory()->for($vehicle)->create([
            'filled_on' => '2026-02-01', 'odometer_km' => 1400, 'liters' => 35, 'unit_price' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/vehicles/{$vehicle->id}/fuel-entries")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            // Orden descendente: la más reciente primero.
            ->assertJsonPath('data.0.odometer_km', 1400)
            ->assertJsonPath('data.0.distance_km', 400)
            ->assertJsonPath('data.0.km_per_liter', 11.43);
    }

    public function test_entry_show_includes_metrics(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $entry = FuelEntry::factory()->for($vehicle)->create(['odometer_km' => 900, 'liters' => 30]);

        Sanctum::actingAs($user);

        $this->getJson("/api/fuel-entries/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $entry->id)
            ->assertJsonPath('data.total', round(30 * (float) $entry->unit_price, 2));
    }

    public function test_foreign_entry_is_forbidden(): void
    {
        $user = User::factory()->create();
        $entry = FuelEntry::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/fuel-entries/{$entry->id}")->assertForbidden();
        $this->deleteJson("/api/fuel-entries/{$entry->id}")->assertForbidden();
    }

    public function test_update_recomputes_total(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create(['odometer_km' => 0]);
        $entry = FuelEntry::factory()->for($vehicle)->create([
            'odometer_km' => 500, 'liters' => 10, 'unit_price' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/fuel-entries/{$entry->id}", [
            'filled_on' => $entry->filled_on->toDateString(),
            'odometer_km' => 2000,
            'liters' => 12,
            'unit_price' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.total', 24)
            ->assertJsonPath('data.odometer_km', 2000);

        $this->assertSame(2000, $vehicle->fresh()->odometer_km);
    }

    public function test_owner_can_delete_entry(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $entry = FuelEntry::factory()->for($vehicle)->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/fuel-entries/{$entry->id}")->assertOk();
        $this->assertDatabaseMissing('fuel_entries', ['id' => $entry->id]);
    }

    public function test_liters_must_be_positive(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/fuel-entries", [
            'filled_on' => '2026-01-15',
            'odometer_km' => 100,
            'liters' => 0,
            'unit_price' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('liters');
    }

    public function test_summary_endpoint_returns_aggregates(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        FuelEntry::factory()->for($vehicle)->create([
            'filled_on' => '2026-01-01', 'odometer_km' => 1000, 'liters' => 40, 'unit_price' => 1,
        ]);
        FuelEntry::factory()->for($vehicle)->create([
            'filled_on' => '2026-02-01', 'odometer_km' => 1400, 'liters' => 35, 'unit_price' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/vehicles/{$vehicle->id}/fuel-summary")
            ->assertOk()
            ->assertJsonPath('data.entries', 2)
            ->assertJsonPath('data.total_liters', 75)
            ->assertJsonPath('data.total_cost', 75)
            ->assertJsonPath('data.distance_km', 400)
            ->assertJsonPath('data.km_per_liter', 11.43)
            ->assertJsonPath('data.last_filled_on', '2026-02-01');
    }
}

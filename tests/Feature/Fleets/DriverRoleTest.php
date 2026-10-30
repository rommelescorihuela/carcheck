<?php

namespace Tests\Feature\Fleets;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_log_fuel_and_the_entry_is_attributed(): void
    {
        [$organization, $driver, $vehicle] = $this->fleet();

        Sanctum::actingAs($driver);

        $this->postJson("/api/vehicles/{$vehicle->id}/fuel-entries", [
            'filled_on' => today()->toDateString(),
            'odometer_km' => 1200,
            'liters' => 40,
            'unit_price' => 1.5,
        ])
            ->assertCreated()
            ->assertJsonPath('data.recorded_by', $driver->name);

        $this->assertDatabaseHas('fuel_entries', [
            'vehicle_id' => $vehicle->id,
            'user_id' => $driver->id,
        ]);
    }

    public function test_driver_can_log_maintenance(): void
    {
        [, $driver, $vehicle] = $this->fleet();

        Sanctum::actingAs($driver);

        $this->postJson("/api/vehicles/{$vehicle->id}/maintenance-records", [
            'performed_on' => today()->toDateString(),
            'odometer_km' => 1300,
            'provider' => 'Taller del barrio',
        ])->assertCreated();

        $this->assertDatabaseHas('maintenance_records', [
            'vehicle_id' => $vehicle->id,
            'user_id' => $driver->id,
        ]);
    }

    public function test_driver_cannot_create_vehicles(): void
    {
        [$organization, $driver] = $this->fleet();

        Sanctum::actingAs($driver);

        $this->postJson('/api/vehicles', [
            'make' => 'Ford',
            'model' => 'Ranger',
            'organization_id' => $organization->id,
        ])->assertForbidden();
    }

    public function test_driver_cannot_invite_members(): void
    {
        Mail::fake();

        [$organization, $driver] = $this->fleet();

        Sanctum::actingAs($driver);

        $this->postJson("/api/organizations/{$organization->id}/invitations", [
            'email' => 'nuevo@example.com',
            'role' => 'member',
        ])->assertForbidden();
    }

    public function test_driver_can_correct_own_entry_but_not_someone_elses(): void
    {
        [, $driver, $vehicle] = $this->fleet();

        $own = $vehicle->fuelEntries()->create([
            'user_id' => $driver->id,
            'filled_on' => today()->toDateString(),
            'odometer_km' => 1000,
            'liters' => 30,
            'unit_price' => 1.2,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($driver);

        $this->patchJson("/api/fuel-entries/{$own->id}", [
            'filled_on' => today()->toDateString(),
            'odometer_km' => 1010,
            'liters' => 31,
            'unit_price' => 1.2,
        ])->assertOk();

        $foreign = $vehicle->fuelEntries()->create([
            'user_id' => $vehicle->owner->owner_id,
            'filled_on' => today()->toDateString(),
            'odometer_km' => 1100,
            'liters' => 20,
            'unit_price' => 1.2,
            'currency' => 'USD',
        ]);

        $this->patchJson("/api/fuel-entries/{$foreign->id}", [
            'filled_on' => today()->toDateString(),
            'odometer_km' => 1110,
            'liters' => 21,
            'unit_price' => 1.2,
        ])->assertForbidden();
    }

    public function test_owner_can_promote_a_member_to_driver(): void
    {
        [$organization, $driver] = $this->fleet();

        Sanctum::actingAs($organization->owner);

        $this->patchJson("/api/organizations/{$organization->id}/members/{$driver->id}", [
            'role' => 'driver',
        ])->assertOk();

        $this->assertSame('driver', $organization->fresh()->roleOf($driver));
    }

    /**
     * @return array{0: Organization, 1: User, 2: Vehicle}
     */
    private function fleet(): array
    {
        $organization = Organization::factory()->create();
        $driver = User::factory()->create();

        $organization->members()->attach($driver->id, [
            'role' => 'driver',
            'joined_at' => now(),
        ]);

        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        return [$organization, $driver, $vehicle];
    }
}

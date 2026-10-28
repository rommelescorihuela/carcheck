<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceType;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenancePlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_plans(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();
        $type = MaintenanceType::factory()->create();
        $vehicle->maintenancePlans()->create(['maintenance_type_id' => $type->id, 'interval_km' => 10000]);

        Sanctum::actingAs($member);

        $this->getJson("/api/vehicles/{$vehicle->id}/maintenance-plans")
            ->assertOk()
            ->assertJsonPath('data.0.interval_km', 10000);
    }

    public function test_owner_can_upsert_plan(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $type = MaintenanceType::factory()->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/vehicles/{$vehicle->id}/maintenance-plans/{$type->id}", [
            'interval_km' => 8000,
            'interval_months' => 6,
        ])->assertCreated()->assertJsonPath('data.interval_km', 8000);

        // Upsert de nuevo: actualiza, no duplica.
        $this->putJson("/api/vehicles/{$vehicle->id}/maintenance-plans/{$type->id}", [
            'interval_km' => 9000,
        ])->assertOk()->assertJsonPath('data.interval_km', 9000);

        $this->assertSame(1, $vehicle->maintenancePlans()->count());
    }

    public function test_plain_member_cannot_upsert_plan(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();
        $type = MaintenanceType::factory()->create();

        Sanctum::actingAs($member);

        $this->putJson("/api/vehicles/{$vehicle->id}/maintenance-plans/{$type->id}", [
            'interval_km' => 8000,
        ])->assertForbidden();
    }

    public function test_cannot_upsert_on_foreign_vehicle(): void
    {
        $user = User::factory()->create();
        $foreign = Vehicle::factory()->create();
        $type = MaintenanceType::factory()->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/vehicles/{$foreign->id}/maintenance-plans/{$type->id}", [
            'interval_km' => 8000,
        ])->assertForbidden();
    }

    public function test_cannot_use_foreign_type(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $foreignType = MaintenanceType::factory()->forUser(User::factory()->create())->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/vehicles/{$vehicle->id}/maintenance-plans/{$foreignType->id}", [
            'interval_km' => 8000,
        ])->assertForbidden();
    }

    public function test_owner_can_delete_plan(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $type = MaintenanceType::factory()->create();
        $vehicle->maintenancePlans()->create(['maintenance_type_id' => $type->id, 'interval_km' => 10000]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/vehicles/{$vehicle->id}/maintenance-plans/{$type->id}")->assertOk();
        $this->assertSame(0, $vehicle->maintenancePlans()->count());
    }
}

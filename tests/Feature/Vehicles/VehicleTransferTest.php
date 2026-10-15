<?php

namespace Tests\Feature\Vehicles;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_vehicle_can_be_transferred_to_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->attach($user, ['role' => 'admin', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/transfer", [
            'owner_type' => 'organization',
            'owner_id' => $organization->id,
        ])->assertOk()->assertJsonPath('data.owner_id', $organization->id);

        $this->assertTrue($vehicle->fresh()->owner->is($organization));
    }

    public function test_organization_vehicle_can_be_transferred_to_personal_by_owner(): void
    {
        $organization = Organization::factory()->create();
        $owner = $organization->owner;
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/vehicles/{$vehicle->id}/transfer", [
            'owner_type' => 'user',
            'owner_id' => $owner->id,
        ])->assertOk()->assertJsonPath('data.owner_id', $owner->id);

        $this->assertTrue($vehicle->fresh()->owner->is($owner));
    }

    public function test_organization_admin_cannot_take_vehicle_to_personal(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $organization->members()->attach($admin, ['role' => 'admin', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs($admin);

        $this->postJson("/api/vehicles/{$vehicle->id}/transfer", [
            'owner_type' => 'user',
            'owner_id' => $admin->id,
        ])->assertForbidden();
    }

    public function test_cannot_transfer_to_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/transfer", [
            'owner_type' => 'user',
            'owner_id' => $other->id,
        ])->assertForbidden();
    }

    public function test_cannot_transfer_to_organization_where_not_admin(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->attach($user, ['role' => 'member', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/transfer", [
            'owner_type' => 'organization',
            'owner_id' => $organization->id,
        ])->assertForbidden();
    }

    public function test_cannot_transfer_vehicle_you_cannot_manage(): void
    {
        $user = User::factory()->create();
        $foreign = Vehicle::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$foreign->id}/transfer", [
            'owner_type' => 'user',
            'owner_id' => $user->id,
        ])->assertForbidden();
    }
}

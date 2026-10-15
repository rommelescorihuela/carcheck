<?php

namespace Tests\Feature\Vehicles;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_personal_vehicle(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/vehicles', ['make' => 'Toyota', 'model' => 'Corolla'])
            ->assertCreated()
            ->assertJsonPath('data.make', 'Toyota')
            ->assertJsonPath('data.owner_type', User::class)
            ->assertJsonPath('data.owner_id', $user->id);

        $this->assertDatabaseHas('vehicles', ['make' => 'Toyota', 'owner_id' => $user->id]);
    }

    public function test_owner_can_create_organization_vehicle(): void
    {
        $organization = Organization::factory()->create();
        Sanctum::actingAs($organization->owner);

        $this->postJson('/api/vehicles', [
            'make' => 'Ford',
            'model' => 'Ranger',
            'organization_id' => $organization->id,
        ])->assertCreated()->assertJsonPath('data.owner_id', $organization->id);

        $this->assertTrue(Vehicle::firstWhere('make', 'Ford')->owner->is($organization));
    }

    public function test_plain_member_cannot_create_organization_vehicle(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($member);

        $this->postJson('/api/vehicles', [
            'make' => 'Ford',
            'model' => 'Ranger',
            'organization_id' => $organization->id,
        ])->assertForbidden();
    }

    public function test_non_member_cannot_create_organization_vehicle(): void
    {
        $organization = Organization::factory()->create();
        $outsider = User::factory()->create();

        Sanctum::actingAs($outsider);

        $this->postJson('/api/vehicles', [
            'make' => 'Ford',
            'model' => 'Ranger',
            'organization_id' => $organization->id,
        ])->assertForbidden();
    }

    public function test_index_lists_personal_and_organization_vehicles(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->attach($user, ['role' => 'member', 'joined_at' => now()]);

        $personal = Vehicle::factory()->forUser($user)->create();
        $orgVehicle = Vehicle::factory()->forOrganization($organization)->create();
        $foreign = Vehicle::factory()->create();

        Sanctum::actingAs($user);

        $ids = collect($this->getJson('/api/vehicles')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($personal->id));
        $this->assertTrue($ids->contains($orgVehicle->id));
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_index_can_filter_personal_scope(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->attach($user, ['role' => 'member', 'joined_at' => now()]);

        $personal = Vehicle::factory()->forUser($user)->create();
        Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs($user);

        $ids = collect(
            $this->getJson('/api/vehicles?scope=personal')->assertOk()->json('data')
        )->pluck('id');

        $this->assertSame([$personal->id], $ids->all());
    }

    public function test_index_can_filter_by_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->attach($user, ['role' => 'member', 'joined_at' => now()]);

        $orgVehicle = Vehicle::factory()->forOrganization($organization)->create();
        Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $ids = collect(
            $this->getJson("/api/vehicles?organization_id={$organization->id}")
                ->assertOk()
                ->json('data')
        )->pluck('id');

        $this->assertSame([$orgVehicle->id], $ids->all());
    }

    public function test_non_accessible_vehicle_returns_forbidden(): void
    {
        $user = User::factory()->create();
        $foreign = Vehicle::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/vehicles/{$foreign->id}")->assertForbidden();
    }

    public function test_owner_can_update_vehicle(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->patchJson("/api/vehicles/{$vehicle->id}", ['odometer_km' => 12345])
            ->assertOk()
            ->assertJsonPath('data.odometer_km', 12345);
    }

    public function test_plain_member_cannot_update_organization_vehicle(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs($member);

        $this->patchJson("/api/vehicles/{$vehicle->id}", ['make' => 'Honda'])->assertForbidden();
    }

    public function test_admin_can_update_organization_vehicle(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $organization->members()->attach($admin, ['role' => 'admin', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/vehicles/{$vehicle->id}", ['make' => 'Honda'])
            ->assertOk()
            ->assertJsonPath('data.make', 'Honda');
    }

    public function test_admin_can_delete_organization_vehicle(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $organization->members()->attach($admin, ['role' => 'admin', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/vehicles/{$vehicle->id}")->assertOk();

        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_member_can_view_organization_vehicle(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs($member);

        $this->getJson("/api/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $vehicle->id);
    }

    public function test_owner_can_delete_vehicle(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/vehicles/{$vehicle->id}")->assertOk();

        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_validation_errors(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/vehicles', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['make', 'model']);

        $this->postJson('/api/vehicles', ['make' => 'X', 'model' => 'Y', 'type' => 'spaceship'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');

        $this->postJson('/api/vehicles', ['make' => 'X', 'model' => 'Y', 'year' => 1800])
            ->assertStatus(422)
            ->assertJsonValidationErrors('year');
    }
}

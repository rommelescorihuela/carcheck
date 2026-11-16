<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_rename_the_organization(): void
    {
        $organization = Organization::factory()->create(['name' => 'Taller Uno']);
        Sanctum::actingAs($organization->owner);

        $this->patchJson("/api/organizations/{$organization->id}", ['name' => 'Taller Central'])
            ->assertOk()
            ->assertJsonPath('organization.name', 'Taller Central');

        $this->assertSame('Taller Central', $organization->fresh()->name);
    }

    public function test_admin_can_rename_but_plain_member_cannot(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($admin, ['role' => 'admin', 'joined_at' => now()]);
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/organizations/{$organization->id}", ['name' => 'Renombrada'])
            ->assertOk();

        Sanctum::actingAs($member);
        $this->patchJson("/api/organizations/{$organization->id}", ['name' => 'Otra vez'])
            ->assertForbidden();
    }

    public function test_owner_can_delete_the_organization_and_its_vehicles_are_soft_deleted(): void
    {
        $organization = Organization::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'owner_type' => $organization->getMorphClass(),
            'owner_id' => $organization->id,
        ]);

        Sanctum::actingAs($organization->owner);

        $this->deleteJson("/api/organizations/{$organization->id}")->assertOk();

        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_plain_member_cannot_delete_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($member);

        $this->deleteJson("/api/organizations/{$organization->id}")->assertForbidden();
    }

    public function test_owner_can_transfer_ownership_to_a_member(): void
    {
        $organization = Organization::factory()->create();
        $owner = $organization->owner;
        $target = User::factory()->create();
        $organization->members()->attach($target, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/organizations/{$organization->id}/transfer", ['user_id' => $target->id])
            ->assertOk();

        $organization->refresh();

        $this->assertSame($target->id, $organization->owner_id);
        $this->assertSame('owner', $organization->roleOf($target));
        $this->assertSame('admin', $organization->roleOf($owner));
    }

    public function test_ownership_cannot_be_transferred_to_a_non_member(): void
    {
        $organization = Organization::factory()->create();
        $outsider = User::factory()->create();

        Sanctum::actingAs($organization->owner);

        $this->postJson("/api/organizations/{$organization->id}/transfer", ['user_id' => $outsider->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_member_can_leave_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($member);

        $this->deleteJson("/api/organizations/{$organization->id}/leave")->assertOk();

        $this->assertFalse($organization->fresh()->hasMember($member));
    }

    public function test_owner_cannot_leave_without_transferring_or_deleting(): void
    {
        $organization = Organization::factory()->create();
        $owner = $organization->owner;

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/organizations/{$organization->id}/leave")
            ->assertStatus(422)
            ->assertJsonValidationErrors('organization');
    }
}

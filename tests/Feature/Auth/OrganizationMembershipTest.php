<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_becomes_owner_of_new_organization(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/organizations', ['name' => 'Taller Uno'])
            ->assertCreated()
            ->assertJsonPath('organization.name', 'Taller Uno');

        $organization = Organization::firstWhere('name', 'Taller Uno');

        $this->assertSame($user->id, $organization->owner_id);
        $this->assertSame('owner', $organization->roleOf($user));
    }

    public function test_index_lists_only_organizations_of_the_user(): void
    {
        $user = User::factory()->create();
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $first->members()->attach($user, ['role' => 'member', 'joined_at' => now()]);
        $second->members()->attach($user, ['role' => 'admin', 'joined_at' => now()]);
        $foreign = Organization::factory()->create();

        Sanctum::actingAs($user);

        $ids = collect($this->getJson('/api/organizations')->assertOk()->json('organizations'))
            ->pluck('id');

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $ids->all());
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_non_member_cannot_view_organization(): void
    {
        $user = User::factory()->create();
        $foreign = Organization::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/organizations/{$foreign->id}")->assertForbidden();
    }

    public function test_member_can_list_members(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($member);

        $this->getJson("/api/organizations/{$organization->id}/members")
            ->assertOk()
            ->assertJsonCount(2, 'members');
    }

    public function test_owner_can_change_member_role(): void
    {
        $organization = Organization::factory()->create();
        $owner = $organization->owner;
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/organizations/{$organization->id}/members/{$member->id}", [
            'role' => 'admin',
        ])->assertOk();

        $this->assertSame('admin', $organization->fresh()->roleOf($member));
    }

    public function test_owner_role_cannot_be_changed(): void
    {
        $organization = Organization::factory()->create();
        $owner = $organization->owner;

        Sanctum::actingAs($owner);

        $this->patchJson("/api/organizations/{$organization->id}/members/{$owner->id}", [
            'role' => 'member',
        ])->assertStatus(422);
    }

    public function test_role_cannot_be_set_to_owner(): void
    {
        $organization = Organization::factory()->create();
        $owner = $organization->owner;
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/organizations/{$organization->id}/members/{$member->id}", [
            'role' => 'owner',
        ])->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_plain_member_cannot_change_roles(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $other = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);
        $organization->members()->attach($other, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($member);

        $this->patchJson("/api/organizations/{$organization->id}/members/{$other->id}", [
            'role' => 'admin',
        ])->assertForbidden();
    }

    public function test_owner_can_remove_member_but_not_himself(): void
    {
        $organization = Organization::factory()->create();
        $owner = $organization->owner;
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/organizations/{$organization->id}/members/{$member->id}")
            ->assertOk();
        $this->assertFalse($organization->fresh()->hasMember($member));

        $this->deleteJson("/api/organizations/{$organization->id}/members/{$owner->id}")
            ->assertStatus(422);
    }

    public function test_admin_can_remove_member(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($admin, ['role' => 'admin', 'joined_at' => now()]);
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/organizations/{$organization->id}/members/{$member->id}")
            ->assertOk();
        $this->assertFalse($organization->fresh()->hasMember($member));
    }
}

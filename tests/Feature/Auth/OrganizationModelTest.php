<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_organization_with_owner_as_member(): void
    {
        $organization = Organization::factory()->create();

        $this->assertSame('owner', $organization->roleOf($organization->owner));
        $this->assertTrue($organization->hasMember($organization->owner));
        $this->assertNotEmpty($organization->slug);
    }

    public function test_creating_organization_makes_owner_a_member(): void
    {
        $owner = User::factory()->create();

        // Simula cualquier punto de entrada (p. ej. el panel Filament), que no
        // pasa por OrganizationController.
        $organization = Organization::create([
            'name' => 'Taller Panel',
            'owner_id' => $owner->id,
        ]);

        $this->assertTrue($organization->hasMember($owner));
        $this->assertSame('owner', $organization->roleOf($owner));
    }

    public function test_user_relationships_resolve_memberships(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->attach($user, ['role' => 'member', 'joined_at' => now()]);

        $this->assertTrue($user->belongsToOrganization($organization));
        $this->assertSame('member', $user->roleIn($organization));
        $this->assertTrue($user->organizations()->whereKey($organization->id)->exists());
    }

    public function test_unique_slug_appends_suffix_on_collision(): void
    {
        Organization::factory()->create(['name' => 'Taller Central']);

        $this->assertSame('taller-central-2', Organization::uniqueSlug('Taller Central'));
    }

    public function test_member_can_view_but_not_update_organization(): void
    {
        $member = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        $this->assertTrue($member->can('view', $organization));
        $this->assertFalse($member->can('update', $organization));
        $this->assertFalse($member->can('delete', $organization));
    }

    public function test_owner_can_update_and_delete_organization(): void
    {
        $organization = Organization::factory()->create();
        $owner = $organization->owner;

        $this->assertTrue($owner->can('update', $organization));
        $this->assertTrue($owner->can('delete', $organization));
        $this->assertTrue($owner->can('changeRole', $organization));
    }

    public function test_admin_can_update_and_manage_members_but_not_delete(): void
    {
        $admin = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->attach($admin, ['role' => 'admin', 'joined_at' => now()]);

        $this->assertTrue($admin->can('view', $organization));
        $this->assertTrue($admin->can('update', $organization));
        $this->assertTrue($admin->can('manageMembers', $organization));
        $this->assertFalse($admin->can('delete', $organization));
        $this->assertFalse($admin->can('changeRole', $organization));
    }

    public function test_non_member_cannot_view_organization(): void
    {
        $outsider = User::factory()->create();
        $organization = Organization::factory()->create();

        $this->assertFalse($outsider->can('view', $organization));
    }
}

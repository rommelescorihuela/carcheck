<?php

namespace Tests\Feature\Billing;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_creation_is_blocked_at_the_plan_limit(): void
    {
        $plan = Plan::factory()->create(['max_vehicles' => 1, 'max_members' => null]);
        $user = User::factory()->create();
        Subscription::factory()->forUser($user)->create(['plan_id' => $plan->id]);
        Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/vehicles', ['make' => 'Toyota', 'model' => 'Corolla'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan');

        $this->assertSame(1, $user->vehicles()->count());
    }

    public function test_vehicle_creation_below_the_limit_works(): void
    {
        $plan = Plan::factory()->create(['max_vehicles' => 2]);
        $user = User::factory()->create();
        Subscription::factory()->forUser($user)->create(['plan_id' => $plan->id]);
        Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/vehicles', ['make' => 'Toyota', 'model' => 'Corolla'])
            ->assertCreated();
    }

    public function test_owner_without_subscription_falls_back_to_the_cheapest_active_plan(): void
    {
        Plan::factory()->create(['max_vehicles' => 1, 'is_active' => true, 'sort' => 1]);

        $user = User::factory()->create();
        Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/vehicles', ['make' => 'Toyota', 'model' => 'Corolla'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan');
    }

    public function test_expired_subscription_blocks_new_vehicles(): void
    {
        $plan = Plan::factory()->create(['max_vehicles' => 5]);
        $user = User::factory()->create();
        Subscription::factory()->forUser($user)->expired()->create(['plan_id' => $plan->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/vehicles', ['make' => 'Toyota', 'model' => 'Corolla'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('subscription');
    }

    public function test_organization_vehicle_limit_applies_to_the_organization_plan(): void
    {
        $plan = Plan::factory()->create(['max_vehicles' => 1]);
        $organization = Organization::factory()->create();
        Subscription::factory()->forOrganization($organization)->create(['plan_id' => $plan->id]);
        Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs($organization->owner);

        $this->postJson('/api/vehicles', [
            'make' => 'Ford',
            'model' => 'Ranger',
            'organization_id' => $organization->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan');
    }

    public function test_pending_invitations_count_against_the_member_limit(): void
    {
        Mail::fake();

        $plan = Plan::factory()->create(['max_members' => 3]);
        $organization = Organization::factory()->create();
        Subscription::factory()->forOrganization($organization)->create(['plan_id' => $plan->id]);

        Sanctum::actingAs($organization->owner);

        $this->postJson("/api/organizations/{$organization->id}/invitations", [
            'email' => 'uno@example.com',
            'role' => 'member',
        ])->assertCreated();

        $this->postJson("/api/organizations/{$organization->id}/invitations", [
            'email' => 'dos@example.com',
            'role' => 'member',
        ])->assertCreated();

        $this->postJson("/api/organizations/{$organization->id}/invitations", [
            'email' => 'tres@example.com',
            'role' => 'member',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(2, $organization->invitations()->whereNull('accepted_at')->count());
    }

    public function test_accepting_an_invitation_respects_the_member_limit(): void
    {
        $plan = Plan::factory()->create(['max_members' => 1]);
        $organization = Organization::factory()->create();
        Subscription::factory()->forOrganization($organization)->create(['plan_id' => $plan->id]);

        $invitee = User::factory()->create(['email' => 'invitado@example.com']);
        $invitation = Invitation::create([
            'organization_id' => $organization->id,
            'email' => $invitee->email,
            'role' => 'member',
            'token' => hash('sha256', 'token-de-prueba'),
            'expires_at' => now()->addDays(7),
        ]);

        Sanctum::actingAs($invitee);

        $this->postJson('/api/invitations/token-de-prueba/accept')
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertFalse($organization->hasMember($invitee));
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_only_the_owner_can_invite_another_admin(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $organization->members()->attach($admin, ['role' => 'admin', 'joined_at' => now()]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/organizations/{$organization->id}/invitations", [
            'email' => 'nuevo-admin@example.com',
            'role' => 'admin',
        ])->assertForbidden();

        $this->postJson("/api/organizations/{$organization->id}/invitations", [
            'email' => 'nuevo-miembro@example.com',
            'role' => 'member',
        ])->assertCreated();

        Sanctum::actingAs($organization->owner);

        $this->postJson("/api/organizations/{$organization->id}/invitations", [
            'email' => 'admin-de-confianza@example.com',
            'role' => 'admin',
        ])->assertCreated();
    }

    public function test_an_admin_cannot_remove_another_admin_but_the_owner_can(): void
    {
        $organization = Organization::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $organization->members()->attach($first, ['role' => 'admin', 'joined_at' => now()]);
        $organization->members()->attach($second, ['role' => 'admin', 'joined_at' => now()]);

        Sanctum::actingAs($first);

        $this->deleteJson("/api/organizations/{$organization->id}/members/{$second->id}")
            ->assertForbidden();

        Sanctum::actingAs($organization->owner);

        $this->deleteJson("/api/organizations/{$organization->id}/members/{$second->id}")
            ->assertOk();

        $this->assertFalse($organization->hasMember($second));
    }

    public function test_changing_the_organization_owner_keeps_the_membership_invariant(): void
    {
        $organization = Organization::factory()->create();
        $previous = $organization->owner;
        $next = User::factory()->create();

        $organization->update(['owner_id' => $next->id]);

        $this->assertSame('owner', $organization->roleOf($next));
        $this->assertSame('admin', $organization->roleOf($previous));
        $this->assertTrue($organization->hasMember($next));
    }

    public function test_transferring_a_vehicle_to_a_full_organization_is_blocked(): void
    {
        $plan = Plan::factory()->create(['max_vehicles' => 1]);
        $organization = Organization::factory()->create();
        Subscription::factory()->forOrganization($organization)->create(['plan_id' => $plan->id]);
        Vehicle::factory()->forOrganization($organization)->create();

        $owner = $organization->owner;
        $vehicle = Vehicle::factory()->forUser($owner)->create();

        Sanctum::actingAs($owner);

        $this->postJson("/api/vehicles/{$vehicle->id}/transfer", [
            'owner_type' => 'organization',
            'owner_id' => $organization->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan');

        $this->assertSame(1, $organization->vehicles()->count());
        $this->assertTrue($vehicle->fresh()->owner->is($owner));
    }
}

<?php

namespace Tests\Feature\Fleets;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regresión: crear una organización y su suscripción por la API debe dejar `owner_type`/`owner_id`
 * rellenos. El morph no es `$fillable`, así que solo funciona si se asocia con la relación.
 */
class OwnershipRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_and_subscription_get_an_owner_through_the_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $organizationId = $this->postJson('/api/organizations', ['name' => 'Flota Sur'])
            ->assertCreated()
            ->json('organization.id');

        $this->assertDatabaseHas('organizations', [
            'id' => $organizationId,
            'owner_id' => $user->id,
        ]);

        $plan = Plan::factory()->create(['max_vehicles' => 5, 'max_members' => 5]);

        $this->postJson('/api/billing/subscription', [
            'plan_id' => $plan->id,
            'organization_id' => $organizationId,
        ])->assertCreated();

        $this->assertDatabaseHas('subscriptions', [
            'owner_type' => (new Organization)->getMorphClass(),
            'owner_id' => $organizationId,
            'plan_id' => $plan->id,
        ]);

        $this->postJson('/api/vehicles', [
            'make' => 'Toyota',
            'model' => 'Hilux',
            'organization_id' => $organizationId,
        ])->assertCreated();

        $this->assertDatabaseHas('vehicles', [
            'owner_type' => (new Organization)->getMorphClass(),
            'owner_id' => $organizationId,
        ]);

        $this->assertSame(1, Vehicle::where('owner_id', $organizationId)->count());
    }
}

<?php

namespace Tests\Feature\Billing;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_plans_endpoint_lists_active_plans(): void
    {
        $this->seed(PlanSeeder::class);
        Plan::factory()->inactive()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/billing/plans')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.code', 'basico')
            ->assertJsonPath('data.0.interval_label', 'Mensual');
    }

    public function test_first_subscription_starts_a_free_trial(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price' => 7.99, 'trial_days' => 14]);

        Sanctum::actingAs($user);

        $this->postJson('/api/billing/subscription', ['plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.effective_status', 'trialing')
            ->assertJsonPath('data.trial_days_left', 14)
            ->assertJsonPath('data.has_access', true);
    }

    public function test_second_subscription_without_trial_is_pending_payment(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['trial_days' => 14]);

        // Historial: ya usó la prueba y se venció.
        Subscription::factory()->forUser($user)->create([
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'trial_ends_at' => now()->subMonth(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/billing/subscription', ['plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.has_access', false);
    }

    public function test_plan_without_trial_requires_payment(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->withoutTrial()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/billing/subscription', ['plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment');
    }

    public function test_cannot_start_a_second_subscription_while_current_grants_access(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        Subscription::factory()->forUser($user)->trialing(10)->create(['plan_id' => $plan->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/billing/subscription', ['plan_id' => $plan->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_inactive_plan_cannot_be_subscribed(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->inactive()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/billing/subscription', ['plan_id' => $plan->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_user_can_change_plan(): void
    {
        $user = User::factory()->create();
        $current = Plan::factory()->create(['trial_days' => 0]);
        $target = Plan::factory()->create(['price' => 19.99]);

        Subscription::factory()->forUser($user)->trialing(10)->create(['plan_id' => $current->id]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/billing/subscription', ['plan_id' => $target->id])
            ->assertOk()
            ->assertJsonPath('data.plan.id', $target->id)
            ->assertJsonPath('data.plan.price', '19.99');
    }

    public function test_cancellation_keeps_access_until_period_end(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->forUser($user)->create([
            'current_period_end' => now()->addDays(10)->toDateString(),
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.status', 'canceled')
            ->assertJsonPath('data.has_access', true)
            ->assertJsonPath('data.days_left', 10);

        $this->assertNotNull($subscription->fresh()->canceled_at);
    }

    public function test_overview_reports_usage_and_manage_permission(): void
    {
        $user = User::factory()->create();
        Vehicle::factory()->forUser($user)->count(2)->create();
        Subscription::factory()->forUser($user)->trialing(10)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.usage.vehicles', 2)
            ->assertJsonPath('data.usage.members', null)
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.subscription.effective_status', 'trialing');
    }

    public function test_overview_without_subscription_is_empty(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.subscription', null)
            ->assertJsonPath('data.usage.vehicles', 0);
    }

    public function test_organization_member_can_view_but_not_manage(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        $plan = Plan::factory()->create();
        Subscription::factory()->forOrganization($organization)->trialing(10)->create([
            'plan_id' => $plan->id,
        ]);

        Sanctum::actingAs($member);

        $this->getJson('/api/billing/subscription?organization_id='.$organization->id)
            ->assertOk()
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.usage.members', 2);

        $this->postJson('/api/billing/subscription', [
            'plan_id' => $plan->id,
            'organization_id' => $organization->id,
        ])->assertForbidden();
    }

    public function test_organization_owner_can_subscribe(): void
    {
        $organization = Organization::factory()->create();
        $plan = Plan::factory()->create();

        Sanctum::actingAs($organization->owner);

        $this->postJson('/api/billing/subscription', [
            'plan_id' => $plan->id,
            'organization_id' => $organization->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'trialing');

        $this->assertSame(
            $organization->id,
            Subscription::query()->latest('id')->first()->owner_id,
        );
    }

    public function test_foreign_organization_is_forbidden(): void
    {
        $organization = Organization::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/billing/subscription?organization_id='.$organization->id)
            ->assertForbidden();
    }
}

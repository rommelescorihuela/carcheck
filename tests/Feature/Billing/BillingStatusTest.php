<?php

namespace Tests\Feature\Billing;

use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingStatusTest extends TestCase
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

    public function test_active_trial_reports_days_left(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->forUser($user)->trialing(10)->create();

        $billing = app(Billing::class);

        $this->assertSame('trialing', $billing->effectiveStatus($subscription));
        $this->assertSame(10, $billing->trialDaysLeft($subscription));
        $this->assertTrue($billing->hasAccess($subscription));
    }

    public function test_expired_trial_does_not_grant_access(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->forUser($user)->create([
            'status' => 'trialing',
            'trial_ends_at' => now()->subDay(),
        ]);

        $billing = app(Billing::class);

        $this->assertSame('expired', $billing->effectiveStatus($subscription));
        $this->assertSame(0, $billing->trialDaysLeft($subscription));
        $this->assertFalse($billing->hasAccess($subscription));
    }

    public function test_active_subscription_with_future_period_grants_access(): void
    {
        $subscription = Subscription::factory()->create([
            'current_period_end' => now()->addDays(5)->toDateString(),
        ]);

        $billing = app(Billing::class);

        $this->assertSame('active', $billing->effectiveStatus($subscription));
        $this->assertSame(5, $billing->daysLeft($subscription));
        $this->assertTrue($billing->hasAccess($subscription));
    }

    public function test_active_subscription_with_past_period_is_expired(): void
    {
        $subscription = Subscription::factory()->expired()->create();

        $billing = app(Billing::class);

        $this->assertSame('expired', $billing->effectiveStatus($subscription));
        $this->assertFalse($billing->hasAccess($subscription));
    }

    public function test_canceled_subscription_keeps_access_until_period_end(): void
    {
        $subscription = Subscription::factory()->canceled()->create([
            'current_period_end' => now()->addDays(10)->toDateString(),
        ]);

        $billing = app(Billing::class);

        $this->assertSame('canceled', $billing->effectiveStatus($subscription));
        $this->assertTrue($billing->hasAccess($subscription));
    }

    public function test_canceled_subscription_without_period_does_not_grant_access(): void
    {
        $subscription = Subscription::factory()->canceled()->create([
            'current_period_end' => now()->subDay()->toDateString(),
        ]);

        $this->assertFalse(app(Billing::class)->hasAccess($subscription));
    }

    public function test_pending_payment_does_not_grant_access(): void
    {
        $subscription = Subscription::factory()->pendingPayment()->create();

        $billing = app(Billing::class);

        $this->assertSame('pending_payment', $billing->effectiveStatus($subscription));
        $this->assertFalse($billing->hasAccess($subscription));
    }
}

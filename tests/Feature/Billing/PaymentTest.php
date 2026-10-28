<?php

namespace Tests\Feature\Billing;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Carbon::setTestNow('2026-06-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_user_can_report_a_payment(): void
    {
        [$user, $subscription] = $this->pendingSubscription(price: 7.99);

        Sanctum::actingAs($user);

        $this->postJson('/api/billing/payments', [
            'amount' => 7.99,
            'currency' => 'USD',
            'method' => 'zelle',
            'reference' => 'ZL-123',
            'paid_on' => '2026-06-15',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.method_label', 'Zelle')
            ->assertJsonPath('data.amount_base', 7.99)
            ->assertJsonPath('data.proof', null);

        $this->assertSame(1, $subscription->payments()->count());
    }

    public function test_payment_in_local_currency_uses_exchange_rate(): void
    {
        [$user] = $this->pendingSubscription(price: 7.99);

        Sanctum::actingAs($user);

        $this->postJson('/api/billing/payments', [
            'amount' => 320,
            'currency' => 'VES',
            'exchange_rate' => 40,
            'method' => 'mobile_payment',
            'paid_on' => '2026-06-15',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount_base', 8);
    }

    public function test_approval_activates_subscription_and_extends_period(): void
    {
        [$user, $subscription, $plan] = $this->pendingSubscription(price: 7.99);
        $payment = Payment::factory()->for($subscription)->create([
            'amount' => 7.99,
            'paid_on' => '2026-06-20',
        ]);

        Sanctum::actingAs($this->superAdmin());

        $this->postJson("/api/billing/payments/{$payment->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $subscription->refresh();

        $this->assertSame('active', $subscription->status->value);
        $this->assertSame('2026-06-20', $subscription->current_period_start->toDateString());
        $this->assertSame('2026-07-20', $subscription->current_period_end->toDateString());
        $this->assertSame('transfer', $subscription->payment_method->value);
        $this->assertNotNull($payment->fresh()->reviewed_at);
        $this->assertNotNull($payment->fresh()->reviewed_by);
    }

    public function test_approval_of_yearly_plan_extends_one_year(): void
    {
        [$user, $subscription] = $this->pendingSubscription(price: 79, yearly: true);
        $payment = Payment::factory()->for($subscription)->create([
            'amount' => 79,
            'paid_on' => '2026-06-15',
        ]);

        Sanctum::actingAs($this->superAdmin());

        $this->postJson("/api/billing/payments/{$payment->id}/approve")->assertOk();

        $this->assertSame('2027-06-15', $subscription->fresh()->current_period_end->toDateString());
    }

    public function test_renewal_chains_onto_the_current_period(): void
    {
        [$user, $subscription] = $this->pendingSubscription(price: 7.99);
        $subscription->update([
            'status' => 'active',
            'current_period_start' => '2026-06-01',
            'current_period_end' => '2026-07-01',
        ]);

        $payment = Payment::factory()->for($subscription)->create([
            'amount' => 7.99,
            'paid_on' => '2026-06-15',
        ]);

        Sanctum::actingAs($this->superAdmin());

        $this->postJson("/api/billing/payments/{$payment->id}/approve")->assertOk();

        $this->assertSame('2026-07-01', $subscription->fresh()->current_period_start->toDateString());
        $this->assertSame('2026-08-01', $subscription->fresh()->current_period_end->toDateString());
    }

    public function test_payment_that_does_not_cover_the_plan_is_rejected(): void
    {
        [$user, $subscription] = $this->pendingSubscription(price: 7.99);
        $payment = Payment::factory()->for($subscription)->create(['amount' => 5]);

        Sanctum::actingAs($this->superAdmin());

        $this->postJson("/api/billing/payments/{$payment->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertSame('pending', $payment->fresh()->status->value);
        $this->assertSame('pending_payment', $subscription->fresh()->status->value);
    }

    public function test_only_super_admin_can_approve_or_reject(): void
    {
        [$user, $subscription] = $this->pendingSubscription(price: 7.99);
        $payment = Payment::factory()->for($subscription)->create(['amount' => 7.99]);

        Sanctum::actingAs($user);

        $this->postJson("/api/billing/payments/{$payment->id}/approve")->assertForbidden();
        $this->postJson("/api/billing/payments/{$payment->id}/reject")->assertForbidden();
    }

    public function test_super_admin_can_reject_with_a_reason(): void
    {
        [$user, $subscription] = $this->pendingSubscription(price: 7.99);
        $payment = Payment::factory()->for($subscription)->create(['amount' => 7.99]);

        Sanctum::actingAs($this->superAdmin());

        $this->postJson("/api/billing/payments/{$payment->id}/reject", [
            'reason' => 'Comprobante ilegible',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertStringContainsString('Comprobante ilegible', $payment->fresh()->notes);
    }

    public function test_user_can_attach_and_remove_the_proof(): void
    {
        [$user, $subscription] = $this->pendingSubscription(price: 7.99);
        $payment = Payment::factory()->for($subscription)->create(['amount' => 7.99]);

        Sanctum::actingAs($user);

        $this->postJson("/api/billing/payments/{$payment->id}/proof", [
            'file' => UploadedFile::fake()->create('comprobante.pdf', 300, 'application/pdf'),
        ])
            ->assertOk()
            ->assertJsonPath('data.proof.file_name', 'comprobante.pdf');

        $this->deleteJson("/api/billing/payments/{$payment->id}/proof")
            ->assertOk()
            ->assertJsonPath('data.proof', null);

        $this->assertCount(0, $payment->fresh()->getMedia('proof'));
    }

    public function test_payments_are_scoped_to_the_owner(): void
    {
        [$user, $subscription] = $this->pendingSubscription(price: 7.99);
        Payment::factory()->for($subscription)->create(['amount' => 7.99]);

        $other = User::factory()->create();
        Payment::factory()->create(['amount' => 99]);

        Sanctum::actingAs($user);

        $this->getJson('/api/billing/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', '7.99');

        Sanctum::actingAs($other);

        $this->getJson('/api/billing/payments')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_reporting_a_payment_without_subscription_fails(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/billing/payments', [
            'amount' => 10,
            'currency' => 'USD',
            'method' => 'transfer',
            'paid_on' => '2026-06-15',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subscription');
    }

    public function test_already_reviewed_payment_cannot_be_approved_again(): void
    {
        [$user, $subscription] = $this->pendingSubscription(price: 7.99);
        $payment = Payment::factory()->for($subscription)->approved()->create(['amount' => 7.99]);

        Sanctum::actingAs($this->superAdmin());

        $this->postJson("/api/billing/payments/{$payment->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment');
    }

    /**
     * @return array{0: User, 1: Subscription, 2: Plan}
     */
    private function pendingSubscription(float $price, bool $yearly = false): array
    {
        $user = User::factory()->create();
        $plan = $yearly
            ? Plan::factory()->yearly()->create(['price' => $price])
            : Plan::factory()->create(['price' => $price]);

        $subscription = Subscription::factory()->forUser($user)->pendingPayment()->create([
            'plan_id' => $plan->id,
        ]);

        return [$user, $subscription, $plan];
    }

    private function superAdmin(): User
    {
        $this->seed(RolesSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }
}

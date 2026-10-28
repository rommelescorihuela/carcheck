<?php

namespace Tests\Feature\Reports;

use App\Models\FuelEntry;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ExpenseReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ExpenseReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15');
    }

    public function test_totals_by_currency_and_breakdowns(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        $record = MaintenanceRecord::factory()->for($vehicle)->create([
            'performed_on' => '2026-06-01',
            'currency' => 'USD',
        ]);
        MaintenanceItem::factory()->create([
            'maintenance_record_id' => $record->id,
            'quantity' => 2,
            'unit_price' => 10,
        ]);

        FuelEntry::factory()->for($vehicle)->create([
            'filled_on' => '2026-06-10',
            'liters' => 40,
            'unit_price' => 1,
            'currency' => 'USD',
        ]);

        $report = $this->report([$vehicle], [$user], '2026-01-01', '2026-12-31');

        $this->assertSame([
            'currency' => 'USD',
            'maintenance' => 20.0,
            'fuel' => 40.0,
            'total' => 60.0,
            'maintenance_entries' => 1,
            'fuel_entries' => 1,
        ], $report['totals'][0]);

        $this->assertSame($vehicle->id, $report['vehicles'][0]['vehicle']['id']);
        $this->assertSame(60.0, $report['vehicles'][0]['total']);
        $this->assertSame('2026-06', $report['monthly'][0]['month']);
        $this->assertSame(60.0, $report['monthly'][0]['total']);
    }

    public function test_different_currencies_are_never_added_together(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        $record = MaintenanceRecord::factory()->for($vehicle)->create([
            'performed_on' => '2026-06-01',
            'currency' => 'USD',
        ]);
        MaintenanceItem::factory()->create([
            'maintenance_record_id' => $record->id,
            'quantity' => 1,
            'unit_price' => 20,
        ]);

        FuelEntry::factory()->for($vehicle)->create([
            'filled_on' => '2026-06-10',
            'liters' => 40,
            'unit_price' => 1,
            'currency' => 'VES',
        ]);

        $report = $this->report([$vehicle], [$user], '2026-01-01', '2026-12-31');

        $this->assertCount(2, $report['totals']);
        $this->assertSame('USD', $report['totals'][0]['currency']);
        $this->assertSame(20.0, $report['totals'][0]['total']);
        $this->assertSame('VES', $report['totals'][1]['currency']);
        $this->assertSame(40.0, $report['totals'][1]['total']);

        // Una fila por vehículo y moneda.
        $this->assertCount(2, $report['vehicles']);
    }

    public function test_records_outside_the_period_are_ignored(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        MaintenanceRecord::factory()->for($vehicle)->create(['performed_on' => '2025-12-31']);
        FuelEntry::factory()->for($vehicle)->create(['filled_on' => '2026-07-01']);

        $report = $this->report([$vehicle], [$user], '2026-01-01', '2026-06-30');

        $this->assertSame([], $report['totals']);
        $this->assertSame([], $report['vehicles']);
        $this->assertSame([], $report['monthly']);
    }

    public function test_groups_by_vehicle_and_month(): void
    {
        $user = User::factory()->create();
        $first = Vehicle::factory()->forUser($user)->create();
        $second = Vehicle::factory()->forUser($user)->create();

        foreach (['2026-06-02' => 10, '2026-06-20' => 10, '2026-05-05' => 5] as $date => $liters) {
            FuelEntry::factory()->for($first)->create([
                'filled_on' => $date,
                'liters' => $liters,
                'unit_price' => 1,
                'currency' => 'USD',
            ]);
        }

        $record = MaintenanceRecord::factory()->for($second)->create([
            'performed_on' => '2026-06-03',
            'currency' => 'USD',
        ]);
        MaintenanceItem::factory()->create([
            'maintenance_record_id' => $record->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $report = $this->report([$first, $second], [$user], '2026-01-01', '2026-12-31');

        $this->assertSame(['2026-05', '2026-06'], array_column($report['monthly'], 'month'));
        $this->assertSame(5.0, $report['monthly'][0]['total']);
        $this->assertSame(120.0, $report['monthly'][1]['total']);

        // De mayor a menor gasto: el mantenimiento de 100 queda delante.
        $this->assertSame($second->id, $report['vehicles'][0]['vehicle']['id']);
        $this->assertSame(100.0, $report['vehicles'][0]['total']);
        $this->assertSame($first->id, $report['vehicles'][1]['vehicle']['id']);
        $this->assertSame(25.0, $report['vehicles'][1]['total']);
    }

    public function test_billing_counts_only_approved_payments_of_the_owner_in_the_period(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $plan = Plan::factory()->create(['currency' => 'USD']);

        $subscription = Subscription::factory()->forUser($user)->create(['plan_id' => $plan->id]);
        Payment::factory()->for($subscription)->approved()->create([
            'amount' => 29,
            'exchange_rate' => 1,
            'paid_on' => '2026-06-05',
        ]);
        Payment::factory()->for($subscription)->create([
            'amount' => 50,
            'exchange_rate' => 1,
            'paid_on' => '2026-06-06',
        ]);
        Payment::factory()->for($subscription)->rejected()->create([
            'amount' => 10,
            'exchange_rate' => 1,
            'paid_on' => '2026-06-07',
        ]);
        Payment::factory()->for($subscription)->approved()->create([
            'amount' => 70,
            'exchange_rate' => 1,
            'paid_on' => '2025-01-01',
        ]);

        $otherSubscription = Subscription::factory()->forUser($other)->create(['plan_id' => $plan->id]);
        Payment::factory()->for($otherSubscription)->approved()->create([
            'amount' => 99,
            'exchange_rate' => 1,
            'paid_on' => '2026-06-08',
        ]);

        $report = $this->report([], [$user], '2026-01-01', '2026-12-31');

        $this->assertSame([
            ['currency' => 'USD', 'payments' => 1, 'total' => 29.0],
        ], $report['billing']);
    }

    public function test_billing_converts_the_payment_using_its_exchange_rate(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['currency' => 'USD']);
        $subscription = Subscription::factory()->forUser($user)->create(['plan_id' => $plan->id]);

        Payment::factory()->for($subscription)->approved()->create([
            'amount' => 1160,
            'currency' => 'VES',
            'exchange_rate' => 40,
            'paid_on' => '2026-06-05',
        ]);

        $report = $this->report([], [$user], '2026-01-01', '2026-12-31');

        $this->assertSame(29.0, $report['billing'][0]['total']);
        $this->assertSame('USD', $report['billing'][0]['currency']);
    }

    public function test_an_empty_period_returns_empty_lists(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        $report = $this->report([$vehicle], [$user], '2026-01-01', '2026-01-31');

        $this->assertSame(['totals' => [], 'vehicles' => [], 'monthly' => [], 'billing' => []], $report);
    }

    /**
     * @param  array<int, Vehicle>  $vehicles
     * @param  array<int, User|Organization>  $owners
     * @return array<string, mixed>
     */
    private function report(array $vehicles, array $owners, string $from, string $to): array
    {
        return app(ExpenseReport::class)->build(
            new Collection($vehicles),
            $owners,
            Carbon::parse($from),
            Carbon::parse($to),
        );
    }
}

<?php

namespace Tests\Feature\Reports;

use App\Models\FuelEntry;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15');
    }

    public function test_default_period_is_the_current_year_to_date(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $this->fuel($vehicle, 40, 1);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reports/expenses');

        $response->assertOk()
            ->assertJsonPath('data.period.from', '2026-01-01')
            ->assertJsonPath('data.period.to', '2026-06-15')
            ->assertJsonPath('data.totals.0.currency', 'USD')
            ->assertJsonPath('data.totals.0.total', 40)
            ->assertJsonPath('data.vehicles.0.vehicle.id', $vehicle->id)
            ->assertJsonPath('data.monthly.0.month', '2026-06')
            ->assertJsonCount(0, 'data.billing');
    }

    public function test_personal_scope_excludes_organization_vehicles(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->id]);

        $this->fuel(Vehicle::factory()->forUser($user)->create(), 40, 1);
        $this->fuel(Vehicle::factory()->forOrganization($organization)->create(), 10, 2);

        Sanctum::actingAs($user);

        $this->getJson('/api/reports/expenses?scope=personal')
            ->assertOk()
            ->assertJsonCount(1, 'data.totals')
            ->assertJsonPath('data.totals.0.fuel', 40);

        $this->getJson("/api/reports/expenses?organization_id={$organization->id}")
            ->assertOk()
            ->assertJsonPath('data.totals.0.fuel', 20);
    }

    public function test_organization_scope_requires_membership(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $stranger->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/reports/expenses?organization_id={$organization->id}")
            ->assertForbidden();
    }

    public function test_vehicle_filter_limits_every_section(): void
    {
        $user = User::factory()->create();
        $first = Vehicle::factory()->forUser($user)->create();
        $second = Vehicle::factory()->forUser($user)->create();
        $this->fuel($first, 40, 1);
        $this->fuel($second, 10, 2);

        Sanctum::actingAs($user);

        $this->getJson("/api/reports/expenses?vehicle_id={$first->id}")
            ->assertOk()
            ->assertJsonPath('data.totals.0.fuel', 40)
            ->assertJsonCount(1, 'data.vehicles')
            ->assertJsonPath('data.vehicles.0.vehicle.id', $first->id)
            ->assertJsonPath('data.monthly.0.fuel', 40);
    }

    public function test_a_foreign_vehicle_is_not_revealed(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($other)->create();
        $this->fuel($vehicle, 40, 1);

        Sanctum::actingAs($user);

        $this->getJson("/api/reports/expenses?vehicle_id={$vehicle->id}")
            ->assertNotFound();
    }

    public function test_the_period_is_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/reports/expenses?from=2026-06-10&to=2026-06-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');

        $this->getJson('/api/reports/expenses?from=no-es-fecha')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from');
    }

    public function test_an_empty_report_returns_empty_lists(): void
    {
        $user = User::factory()->create();
        Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reports/expenses');

        $response->assertOk()
            ->assertJsonPath('data.totals', [])
            ->assertJsonPath('data.vehicles', [])
            ->assertJsonPath('data.monthly', [])
            ->assertJsonPath('data.billing', []);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/reports/expenses')->assertUnauthorized();
    }

    private function fuel(Vehicle $vehicle, float $liters, float $unitPrice): void
    {
        FuelEntry::factory()->for($vehicle)->create([
            'filled_on' => '2026-06-05',
            'liters' => $liters,
            'unit_price' => $unitPrice,
            'currency' => 'USD',
        ]);
    }
}

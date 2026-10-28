<?php

namespace Tests\Feature\Reports;

use App\Models\FuelEntry;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15');
    }

    public function test_export_returns_every_section_of_the_report(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create([
            'make' => 'Toyota',
            'model' => 'Corolla',
            'plate' => 'ABC-123',
        ]);

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
            'filled_on' => '2026-06-05',
            'liters' => 40,
            'unit_price' => 1,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($user);

        $response = $this->get('/api/reports/expenses/export');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename="gastos_2026-01-01_2026-06-15.csv"',
            );

        $csv = $response->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("\"Informe de gastos\"\n", $csv);
        $this->assertStringContainsString("Periodo,2026-01-01,2026-06-15\n", $csv);
        $this->assertStringContainsString("Totales\n", $csv);
        $this->assertStringContainsString("USD,20.00,40.00,60.00,1,1\n", $csv);
        $this->assertStringContainsString("Vehículos\n", $csv);
        $this->assertStringContainsString("\"Toyota Corolla\",ABC-123,USD,20.00,40.00,60.00,1,1\n", $csv);
        $this->assertStringContainsString("Meses\n", $csv);
        $this->assertStringContainsString("2026-06,USD,20.00,40.00,60.00,1,1\n", $csv);
        $this->assertStringContainsString("Suscripciones\n", $csv);
        $this->assertStringContainsString("Moneda,\"Pagos aprobados\",Total\n", $csv);
    }

    public function test_export_keeps_currencies_apart(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        $this->fuel($vehicle, 40, 1, 'USD');
        $this->fuel($vehicle, 10, 2, 'EUR');

        Sanctum::actingAs($user);

        $csv = $this->get('/api/reports/expenses/export')->getContent();

        $this->assertStringContainsString("EUR,0.00,20.00,20.00,0,1\n", $csv);
        $this->assertStringContainsString("USD,0.00,40.00,40.00,0,1\n", $csv);
        $this->assertStringNotContainsString('60.00', $csv);
    }

    public function test_export_applies_the_vehicle_and_period_filters(): void
    {
        $user = User::factory()->create();
        $first = Vehicle::factory()->forUser($user)->create(['make' => 'Toyota', 'model' => 'Yaris']);
        $second = Vehicle::factory()->forUser($user)->create(['make' => 'Ford', 'model' => 'Focus']);

        $this->fuel($first, 40, 1, 'USD', '2026-06-05');
        $this->fuel($second, 10, 1, 'USD', '2026-06-10');
        $this->fuel($second, 7, 1, 'USD', '2025-03-01');

        Sanctum::actingAs($user);

        $csv = $this->get("/api/reports/expenses/export?vehicle_id={$first->id}&from=2026-01-01&to=2026-12-31")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Toyota Yaris', $csv);
        $this->assertStringNotContainsString('Ford Focus', $csv);
        $this->assertStringContainsString("USD,0.00,40.00,40.00,0,1\n", $csv);
        $this->assertStringNotContainsString('10.00', $csv);
    }

    public function test_export_handles_a_vehicle_without_plate(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create([
            'make' => 'Toyota',
            'model' => 'Corolla',
            'plate' => null,
        ]);

        $this->fuel($vehicle, 40, 1, 'USD');

        Sanctum::actingAs($user);

        $csv = $this->get('/api/reports/expenses/export')->assertOk()->getContent();

        $this->assertStringContainsString("\"Toyota Corolla\",,USD,0.00,40.00,40.00,0,1\n", $csv);
    }

    public function test_export_hides_foreign_vehicles(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser(User::factory()->create())->create();

        Sanctum::actingAs($user);

        $this->get("/api/reports/expenses/export?vehicle_id={$vehicle->id}")
            ->assertNotFound();
    }

    public function test_export_validates_the_period(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->get('/api/reports/expenses/export?from=2026-06-10&to=2026-06-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_export_requires_authentication(): void
    {
        $this->getJson('/api/reports/expenses/export')->assertUnauthorized();
    }

    public function test_values_that_look_like_formulas_are_neutralized(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create([
            'make' => '=SUM(A1:A2)',
            'model' => 'Corolla',
            'plate' => '+123',
        ]);

        $this->fuel($vehicle, 40, 1, 'USD');

        Sanctum::actingAs($user);

        $csv = $this->get('/api/reports/expenses/export')->getContent();

        $this->assertStringContainsString("\"'=SUM(A1:A2) Corolla\",'+123,USD", $csv);
        $this->assertStringNotContainsString("\n=SUM", $csv);
    }

    private function fuel(
        Vehicle $vehicle,
        float $liters,
        float $unitPrice,
        string $currency = 'USD',
        string $filledOn = '2026-06-05',
    ): void {
        FuelEntry::factory()->for($vehicle)->create([
            'filled_on' => $filledOn,
            'liters' => $liters,
            'unit_price' => $unitPrice,
            'currency' => $currency,
        ]);
    }
}

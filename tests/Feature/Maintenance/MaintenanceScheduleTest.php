<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\MaintenanceSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01');
    }

    private function vehicleWithPlan(int $odometer, array $plan, array $record): Vehicle
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create(['odometer_km' => $odometer]);
        $type = MaintenanceType::factory()->create();
        $vehicle->maintenancePlans()->create(['maintenance_type_id' => $type->id] + $plan);
        MaintenanceRecord::factory()->for($vehicle)->create(['maintenance_type_id' => $type->id] + $record);

        return $vehicle;
    }

    public function test_status_ok_when_far_from_due(): void
    {
        $vehicle = $this->vehicleWithPlan(10000, ['interval_km' => 5000], [
            'odometer_km' => 10000,
            'performed_on' => '2026-05-01',
        ]);

        $due = app(MaintenanceSchedule::class)->dueFor($vehicle);

        $this->assertCount(1, $due);
        $this->assertSame(15000, $due[0]['due_km']);
        $this->assertSame('ok', $due[0]['status']);
        $this->assertSame('plan', $due[0]['source']);
    }

    public function test_status_due_when_km_reached(): void
    {
        $vehicle = $this->vehicleWithPlan(15000, ['interval_km' => 5000], [
            'odometer_km' => 10000,
            'performed_on' => '2026-05-01',
        ]);

        $due = app(MaintenanceSchedule::class)->dueFor($vehicle);

        $this->assertSame('due', $due[0]['status']);
    }

    public function test_status_soon_within_500_km(): void
    {
        $vehicle = $this->vehicleWithPlan(14600, ['interval_km' => 5000], [
            'odometer_km' => 10000,
            'performed_on' => '2026-05-01',
        ]);

        $due = app(MaintenanceSchedule::class)->dueFor($vehicle);

        $this->assertSame('soon', $due[0]['status']);
    }

    public function test_due_date_from_interval_months(): void
    {
        $vehicle = $this->vehicleWithPlan(1000, ['interval_months' => 12], [
            'odometer_km' => 1000,
            'performed_on' => '2024-01-01',
        ]);

        $due = app(MaintenanceSchedule::class)->dueFor($vehicle);

        $this->assertSame('2025-01-01', $due[0]['due_date']);
        $this->assertSame('due', $due[0]['status']);
    }

    public function test_manual_override_takes_precedence(): void
    {
        $vehicle = $this->vehicleWithPlan(10000, ['interval_km' => 5000], [
            'odometer_km' => 10000,
            'performed_on' => '2026-05-01',
            'next_due_km' => 20000,
        ]);

        $due = app(MaintenanceSchedule::class)->dueFor($vehicle);

        $this->assertSame(20000, $due[0]['due_km']);
        $this->assertSame('manual', $due[0]['source']);
    }

    public function test_due_endpoint(): void
    {
        $vehicle = $this->vehicleWithPlan(15000, ['interval_km' => 5000], [
            'odometer_km' => 10000,
            'performed_on' => '2026-05-01',
        ]);

        Sanctum::actingAs($vehicle->owner);

        $this->getJson("/api/vehicles/{$vehicle->id}/maintenance-due")
            ->assertOk()
            ->assertJsonPath('data.0.status', 'due')
            ->assertJsonPath('data.0.maintenance_type.id', fn ($id) => is_int($id));
    }
}

<?php

namespace Tests\Feature\Fleets;

use App\Models\Plan;
use App\Services\Billing;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_offers_a_fleet_tier_and_a_larger_company_tier(): void
    {
        $this->seed(PlanSeeder::class);

        $this->assertDatabaseMissing('plans', ['code' => 'taller']);

        $flota = Plan::firstWhere('code', 'flota');
        $this->assertNotNull($flota);
        $this->assertSame('Flota', $flota->name);
        $this->assertSame(25, $flota->max_vehicles);
        $this->assertSame(10, $flota->max_members);
        $this->assertSame('29.99', (string) $flota->price);

        $empresa = Plan::firstWhere('code', 'empresa');
        $this->assertNotNull($empresa);
        $this->assertSame(75, $empresa->max_vehicles);
        $this->assertSame(25, $empresa->max_members);
        $this->assertSame('59.99', (string) $empresa->price);
    }

    public function test_the_default_plan_keeps_being_the_cheapest_one(): void
    {
        $this->seed(PlanSeeder::class);

        $this->assertSame('basico', app(Billing::class)->defaultPlan()?->code);
    }
}

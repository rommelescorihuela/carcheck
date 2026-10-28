<?php

namespace Tests\Feature\Admin;

use App\Enums\PlanInterval;
use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\PlanResource;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
    }

    public function test_plans_cannot_be_deleted_from_the_panel(): void
    {
        $plan = Plan::factory()->create();

        $this->assertFalse(PlanResource::canDelete($plan));
        $this->assertFalse(PlanResource::canDeleteAny());
    }

    public function test_admin_can_create_a_plan(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreatePlan::class)
            ->fillForm([
                'code' => 'flotilla',
                'name' => 'Flotilla',
                'description' => 'Hasta diez vehículos.',
                'price' => 19.99,
                'currency' => 'USD',
                'interval' => PlanInterval::Yearly->value,
                'trial_days' => 7,
                'max_vehicles' => 10,
                'max_members' => 5,
                'is_active' => true,
                'sort' => 4,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $plan = Plan::where('code', 'flotilla')->firstOrFail();

        $this->assertSame('Flotilla', $plan->name);
        $this->assertSame('19.99', $plan->price);
        $this->assertSame(PlanInterval::Yearly, $plan->interval);
        $this->assertSame(7, $plan->trial_days);
        $this->assertSame(10, $plan->max_vehicles);
        $this->assertSame(5, $plan->max_members);
        $this->assertTrue($plan->is_active);
        $this->assertSame(4, $plan->sort);
    }

    public function test_admin_can_edit_a_plan_without_touching_subscriptions(): void
    {
        $plan = Plan::factory()->create(['code' => 'basico', 'price' => 3.99]);

        Livewire::actingAs($this->admin)
            ->test(EditPlan::class, ['record' => $plan->getRouteKey()])
            ->fillForm([
                'name' => 'Básico Plus',
                'price' => 4.99,
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $plan->refresh();

        $this->assertSame('Básico Plus', $plan->name);
        $this->assertSame('4.99', $plan->price);
        $this->assertFalse($plan->is_active);
        $this->assertSame('basico', $plan->code);
    }

    public function test_code_must_be_unique(): void
    {
        Plan::factory()->create(['code' => 'basico']);

        Livewire::actingAs($this->admin)
            ->test(CreatePlan::class)
            ->fillForm([
                'code' => 'basico',
                'name' => 'Duplicado',
                'price' => 5,
                'currency' => 'USD',
                'interval' => PlanInterval::Monthly->value,
                'trial_days' => 0,
                'is_active' => true,
                'sort' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['code' => 'unique']);

        $this->assertSame(1, Plan::where('code', 'basico')->count());
    }

    public function test_editing_a_plan_keeps_its_own_code(): void
    {
        $plan = Plan::factory()->create(['code' => 'familiar']);

        Livewire::actingAs($this->admin)
            ->test(EditPlan::class, ['record' => $plan->getRouteKey()])
            ->fillForm(['code' => 'familiar', 'name' => 'Familiar 2'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Familiar 2', $plan->refresh()->name);
    }
}

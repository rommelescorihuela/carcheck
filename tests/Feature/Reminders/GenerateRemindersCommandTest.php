<?php

namespace Tests\Feature\Reminders;

use App\Models\Document;
use App\Models\Reminder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GenerateRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01');
        Mail::fake();
    }

    public function test_the_command_generates_reminders_for_every_vehicle(): void
    {
        $user = User::factory()->create();
        Document::factory()->for(Vehicle::factory()->forUser($user)->create())->expiring()->create();
        Document::factory()->for(Vehicle::factory()->forUser($user)->create())->expired()->create();
        // Un vehículo sin nada que recordar no genera filas.
        Vehicle::factory()->forUser($user)->create();

        $this->artisan('reminders:generate')
            ->expectsOutput('Recordatorios creados: 2.')
            ->assertSuccessful();

        $this->assertSame(2, Reminder::query()->count());
    }

    public function test_running_the_command_twice_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        Document::factory()->for(Vehicle::factory()->forUser($user)->create())->expiring()->create();

        $this->artisan('reminders:generate')->assertSuccessful();

        $this->artisan('reminders:generate')
            ->expectsOutput('Recordatorios creados: 0.')
            ->assertSuccessful();

        $this->assertSame(1, Reminder::query()->count());
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_it_reports_zero_when_there_is_nothing_to_do(): void
    {
        $this->artisan('reminders:generate')
            ->expectsOutput('Recordatorios creados: 0.')
            ->assertSuccessful();
    }
}

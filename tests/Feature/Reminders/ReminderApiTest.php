<?php

namespace Tests\Feature\Reminders;

use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReminderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01');
        Mail::fake();
    }

    public function test_index_returns_accessible_reminders_ordered_by_urgency(): void
    {
        $user = User::factory()->create();
        $personal = Vehicle::factory()->forUser($user)->create();
        Document::factory()->for($personal)->expired()->create();
        Document::factory()->for($personal)->expiring()->create();

        $this->sync($user);

        $response = $this->getJson('/api/reminders');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.overdue', 1);
        $response->assertJsonPath('meta.upcoming', 1);
        $response->assertJsonPath('meta.total', 2);

        // El vencido va primero.
        $response->assertJsonPath('data.0.status', 'overdue');
        $response->assertJsonPath('data.0.days_left', -10);
        $response->assertJsonPath('data.1.status', 'upcoming');
        $response->assertJsonPath('data.1.days_left', 10);
        $response->assertJsonPath('data.1.vehicle.id', $personal->id);
    }

    public function test_index_with_personal_scope_excludes_organization_vehicles(): void
    {
        $user = User::factory()->create();
        $organization = $this->organization($user);
        Document::factory()->for(Vehicle::factory()->forUser($user)->create())->expiring()->create();
        Document::factory()->for(Vehicle::factory()->forOrganization($organization)->create())->expiring()->create();

        $this->sync($user);

        $this->getJson('/api/reminders')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/reminders?scope=personal')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_organization_scope_requires_membership(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $organization = $this->organization($stranger);
        Document::factory()->for(Vehicle::factory()->forOrganization($organization)->create())->expiring()->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/reminders?organization_id={$organization->id}")
            ->assertForbidden();
    }

    public function test_organization_reminders_are_visible_to_every_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = $this->organization($owner);
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);
        Document::factory()->for(Vehicle::factory()->forOrganization($organization)->create())->expiring()->create();

        $this->sync($member);

        $this->getJson("/api/reminders?organization_id={$organization->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.kind', 'document');
    }

    public function test_a_reminder_of_someone_else_is_hidden_and_cannot_be_dismissed(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($other)->create();
        Document::factory()->for($vehicle)->expiring()->create();
        $this->sync($other);

        $reminder = $vehicle->reminders()->sole();

        Sanctum::actingAs($user);

        $this->getJson('/api/reminders')->assertOk()->assertJsonPath('meta.total', 0);
        $this->postJson("/api/reminders/{$reminder->id}/dismiss")->assertNotFound();
    }

    public function test_dismissing_only_hides_the_reminder_for_that_user(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = $this->organization($owner);
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();
        Document::factory()->for($vehicle)->expiring()->create();

        $this->sync($owner);
        $reminder = $vehicle->reminders()->sole();

        Sanctum::actingAs($owner);
        $this->postJson("/api/reminders/{$reminder->id}/dismiss")
            ->assertOk()
            ->assertJsonPath('message', 'Recordatorio descartado.');

        $this->getJson('/api/reminders')->assertJsonPath('meta.total', 0);

        Sanctum::actingAs($member);
        $this->getJson('/api/reminders')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $reminder->id);
    }

    public function test_dismiss_is_idempotent(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        Document::factory()->for($vehicle)->expiring()->create();
        $this->sync($user);
        $reminder = $vehicle->reminders()->sole();

        Sanctum::actingAs($user);

        $this->postJson("/api/reminders/{$reminder->id}/dismiss")->assertOk();
        $this->postJson("/api/reminders/{$reminder->id}/dismiss")->assertOk();

        $this->assertSame(1, $reminder->dismissals()->count());
    }

    public function test_sync_reports_created_and_total_for_the_scope(): void
    {
        $user = User::factory()->create();
        $organization = $this->organization($user);
        Document::factory()->for(Vehicle::factory()->forUser($user)->create())->expiring()->create();
        $orgVehicle = Vehicle::factory()->forOrganization($organization)->create();
        Document::factory()->for($orgVehicle)->expiring()->create();
        Document::factory()->for($orgVehicle)->expired()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/reminders/sync')
            ->assertOk()
            ->assertJsonPath('data.created', 3)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('message', 'Se crearon 3 recordatorios.');

        // Repetir no crea nada nuevo.
        $this->postJson('/api/reminders/sync')
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.total', 3);
    }

    public function test_sync_with_organization_scope_does_not_touch_personal_vehicles(): void
    {
        $user = User::factory()->create();
        $organization = $this->organization($user);
        $personal = Vehicle::factory()->forUser($user)->create();
        Document::factory()->for($personal)->expiring()->create();
        Document::factory()->for(Vehicle::factory()->forOrganization($organization)->create())->expiring()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/reminders/sync?organization_id={$organization->id}")
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.total', 1);

        $this->assertSame(0, $personal->reminders()->count());
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/reminders')->assertUnauthorized();
        $this->postJson('/api/reminders/sync')->assertUnauthorized();
    }

    private function organization(User $owner): Organization
    {
        return Organization::factory()->create(['owner_id' => $owner->id]);
    }

    private function sync(User $user): void
    {
        Sanctum::actingAs($user);

        $this->postJson('/api/reminders/sync')->assertOk();
    }
}

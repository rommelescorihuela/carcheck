<?php

namespace Tests\Feature\Reminders;

use App\Contracts\PushSender;
use App\Enums\ReminderKind;
use App\Mail\NotificationMail;
use App\Models\Document;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ReminderGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakePushSender;
use Tests\TestCase;

class ReminderGenerationTest extends TestCase
{
    use RefreshDatabase;

    private FakePushSender $push;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01');
        Mail::fake();
        $this->push = new FakePushSender;
        $this->app->instance(PushSender::class, $this->push);
    }

    public function test_document_expiring_soon_creates_a_reminder_and_notifies(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user, ['make' => 'Toyota', 'model' => 'Corolla', 'plate' => 'ABC-123']);
        Document::factory()->for($vehicle)->expiring()->create(['name' => 'Seguro 2026']);

        $created = $this->generator()->generate($vehicle);

        $this->assertSame(1, $created);

        $reminder = $vehicle->reminders()->sole();
        $this->assertSame(ReminderKind::Document, $reminder->kind);
        $this->assertSame('Seguro: por vencer', $reminder->title);
        $this->assertStringContainsString('Seguro 2026', $reminder->body);
        $this->assertStringContainsString('10 días', $reminder->body);
        $this->assertStringContainsString('ABC-123', $reminder->body);
        $this->assertSame('2026-06-11', $reminder->due_on->toDateString());
        $this->assertNull($reminder->due_km);
        $this->assertNotNull($reminder->notified_at);
        $this->assertNull($reminder->overdue_notified_at);

        $this->assertSame(1, $user->notifications()->count());

        $notification = $user->notifications()->sole();
        $this->assertSame('reminders', $notification->data['category']);
        $this->assertSame('/recordatorios', $notification->data['url']);
        $this->assertSame($reminder->id, $notification->data['payload']['reminder_id']);
        $this->assertSame($vehicle->id, $notification->data['payload']['vehicle_id']);

        Mail::assertSent(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo($user->email)
            && $mail->title === 'Seguro: por vencer');
        $this->assertCount(1, $this->push->sent);
    }

    public function test_document_far_from_expiry_and_document_without_expiry_are_ignored(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);
        Document::factory()->for($vehicle)->create(['expires_on' => now()->addDays(60)]);
        Document::factory()->for($vehicle)->withoutExpiry()->create();

        $this->assertSame(0, $this->generator()->generate($vehicle));
        $this->assertSame(0, $vehicle->reminders()->count());
        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_expired_document_is_created_as_overdue_and_notified_only_once(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);
        Document::factory()->for($vehicle)->expired()->create();

        $this->generator()->generate($vehicle);

        $reminder = $vehicle->reminders()->sole();
        $this->assertSame('Seguro: vencido', $reminder->title);
        $this->assertSame('overdue', $reminder->status($vehicle));
        $this->assertNotNull($reminder->notified_at);
        // Nace vencido: no se manda luego un "próximo" que ya no aplica.
        $this->assertNotNull($reminder->overdue_notified_at);

        $this->generator()->generate($vehicle);
        $this->generator()->generate($vehicle);

        $this->assertSame(1, $vehicle->reminders()->count());
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_maintenance_close_by_kilometres_creates_a_reminder(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user, ['odometer_km' => 14500]);
        $type = MaintenanceType::factory()->create(['name' => 'Cambio de aceite']);

        $vehicle->maintenancePlans()->create([
            'maintenance_type_id' => $type->id,
            'interval_km' => 5000,
            'interval_months' => 12,
        ]);
        MaintenanceRecord::factory()->for($vehicle)->create([
            'maintenance_type_id' => $type->id,
            'odometer_km' => 10000,
            'performed_on' => '2026-05-01',
        ]);

        $this->generator()->generate($vehicle);

        $reminder = $vehicle->reminders()->sole();
        $this->assertSame(ReminderKind::Maintenance, $reminder->kind);
        $this->assertSame('Cambio de aceite: próximo', $reminder->title);
        $this->assertSame(15000, $reminder->due_km);
        $this->assertStringContainsString('Faltan 500 km', $reminder->body);
        $this->assertSame(500, $reminder->kmLeft($vehicle));
        $this->assertSame('upcoming', $reminder->status($vehicle));
    }

    public function test_maintenance_becomes_overdue_and_notifies_a_second_time(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user, ['odometer_km' => 14500]);
        $type = MaintenanceType::factory()->create(['name' => 'Cambio de aceite']);

        $vehicle->maintenancePlans()->create([
            'maintenance_type_id' => $type->id,
            'interval_km' => 5000,
            'interval_months' => 12,
        ]);
        MaintenanceRecord::factory()->for($vehicle)->create([
            'maintenance_type_id' => $type->id,
            'odometer_km' => 10000,
            'performed_on' => '2026-05-01',
        ]);

        $this->generator()->generate($vehicle);
        $this->assertSame(1, $user->notifications()->count());

        $vehicle->update(['odometer_km' => 15200]);
        $this->generator()->generate($vehicle);

        $reminder = $vehicle->reminders()->sole();
        $this->assertSame('overdue', $reminder->status($vehicle));
        $this->assertSame('Cambio de aceite: vencido', $reminder->title);
        $this->assertNotNull($reminder->overdue_notified_at);
        $this->assertSame(2, $user->notifications()->count());

        // Las dos notificaciones comparten marca de tiempo: se busca por título, no por orden.
        $titles = $user->notifications()->get()->pluck('data.title');
        $this->assertEqualsCanonicalizing(
            ['Cambio de aceite: próximo', 'Cambio de aceite: vencido'],
            $titles->all(),
        );

        $this->generator()->generate($vehicle);
        $this->assertSame(2, $user->notifications()->count());
    }

    public function test_vehicle_without_maintenance_history_does_not_remind(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);
        $type = MaintenanceType::factory()->create();
        $vehicle->maintenancePlans()->create([
            'maintenance_type_id' => $type->id,
            'interval_km' => 5000,
            'interval_months' => 12,
        ]);

        $this->assertSame(0, $this->generator()->generate($vehicle));
        $this->assertSame(0, $vehicle->reminders()->count());
    }

    public function test_renewing_the_document_removes_the_old_reminder(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);
        $document = Document::factory()->for($vehicle)->expiring()->create();

        $this->generator()->generate($vehicle);
        $this->assertSame(1, $vehicle->reminders()->count());

        $document->update(['expires_on' => now()->addYear()->toDateString()]);

        $this->assertSame(0, $this->generator()->generate($vehicle));
        $this->assertSame(0, $vehicle->reminders()->count());
    }

    public function test_a_dismissed_reminder_is_not_covered_by_a_second_run(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);
        Document::factory()->for($vehicle)->expiring()->create();

        $this->generator()->generate($vehicle);
        $reminder = $vehicle->reminders()->sole();
        $reminder->dismissals()->create(['user_id' => $user->id]);

        $this->generator()->generate($vehicle);

        // Sigue existiendo (la condición no cambió), pero el descarte se conserva.
        $this->assertSame(1, $vehicle->reminders()->count());
        $this->assertSame(1, $reminder->dismissals()->count());
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_a_vehicle_of_an_organization_notifies_every_member_once(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        // La organización ya da de alta al propietario como miembro al crearse.
        $organization = Organization::factory()->create(['owner_id' => $owner->id]);
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        $vehicle = Vehicle::factory()->forOrganization($organization)->create();
        Document::factory()->for($vehicle)->expiring()->create();

        $this->generator()->generate($vehicle);

        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame(1, $member->notifications()->count());
        $this->assertCount(2, $this->push->sent);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function vehicle(User $user, array $attributes = []): Vehicle
    {
        return Vehicle::factory()->forUser($user)->create($attributes);
    }

    private function generator(): ReminderGenerator
    {
        return app(ReminderGenerator::class);
    }
}

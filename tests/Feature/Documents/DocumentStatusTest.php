<?php

namespace Tests\Feature\Documents;

use App\Enums\DocumentKind;
use App\Models\Document;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DocumentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DocumentStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_expired_document_reports_negative_days(): void
    {
        $state = app(DocumentStatus::class)->for(Carbon::parse('2026-06-01'));

        $this->assertSame('expired', $state['status']);
        $this->assertSame(-14, $state['days_left']);
    }

    public function test_document_expiring_within_thirty_days_is_expiring(): void
    {
        $service = app(DocumentStatus::class);

        $this->assertSame('expiring', $service->for(Carbon::parse('2026-06-15'))['status']);
        $this->assertSame('expiring', $service->for(Carbon::parse('2026-07-15'))['status']);
        $this->assertSame(30, $service->for(Carbon::parse('2026-07-15'))['days_left']);
    }

    public function test_document_expiring_later_is_valid(): void
    {
        $state = app(DocumentStatus::class)->for(Carbon::parse('2026-07-16'));

        $this->assertSame('valid', $state['status']);
        $this->assertSame(31, $state['days_left']);
    }

    public function test_document_without_expiry_is_always_valid(): void
    {
        $state = app(DocumentStatus::class)->for(null);

        $this->assertSame('valid', $state['status']);
        $this->assertNull($state['days_left']);
    }

    public function test_summary_counts_by_status_and_finds_next_expiration(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Document::factory()->for($vehicle)->count(2)->create();
        Document::factory()->for($vehicle)->expired()->create();
        Document::factory()->for($vehicle)->expiring()->create([
            'name' => 'Revisión anual',
            'kind' => DocumentKind::Inspection,
            'expires_on' => '2026-06-20',
        ]);
        Document::factory()->for($vehicle)->withoutExpiry()->create();

        $summary = app(DocumentStatus::class)->summary($vehicle);

        $this->assertSame(5, $summary['total']);
        $this->assertSame(3, $summary['valid']);
        $this->assertSame(1, $summary['expiring']);
        $this->assertSame(1, $summary['expired']);
        $this->assertSame('Revisión anual', $summary['next_expiration']['name']);
        $this->assertSame('Revisión', $summary['next_expiration']['kind_label']);
        $this->assertSame('2026-06-20', $summary['next_expiration']['expires_on']);
        $this->assertSame(5, $summary['next_expiration']['days_left']);
    }

    public function test_summary_of_vehicle_without_documents_is_empty(): void
    {
        $vehicle = Vehicle::factory()->forUser(User::factory()->create())->create();

        $summary = app(DocumentStatus::class)->summary($vehicle);

        $this->assertSame(0, $summary['total']);
        $this->assertNull($summary['next_expiration']);
    }
}

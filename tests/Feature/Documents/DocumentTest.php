<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Carbon::setTestNow('2026-06-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_user_can_create_document(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/documents", [
            'kind' => 'insurance',
            'name' => 'Seguro todo riesgo',
            'issuer' => 'Aseguradora Central',
            'reference' => 'POL-1234',
            'issued_on' => '2026-01-01',
            'expires_on' => '2026-07-20',
        ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'insurance')
            ->assertJsonPath('data.kind_label', 'Seguro')
            ->assertJsonPath('data.status', 'valid')
            ->assertJsonPath('data.days_left', 35)
            ->assertJsonPath('data.file', null);
    }

    public function test_status_is_derived_from_expiry(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $document = Document::factory()->for($vehicle)->expired()->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.days_left', -10);
    }

    public function test_index_orders_by_expiry_with_null_last(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Document::factory()->for($vehicle)->withoutExpiry()->create(['name' => 'Título']);
        Document::factory()->for($vehicle)->create([
            'name' => 'Seguro', 'expires_on' => '2026-08-01',
        ]);
        Document::factory()->for($vehicle)->expired()->create([
            'name' => 'Revisión', 'expires_on' => '2026-05-01',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/vehicles/{$vehicle->id}/documents")->assertOk();

        $response->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Revisión')
            ->assertJsonPath('data.1.name', 'Seguro')
            ->assertJsonPath('data.2.name', 'Título');
    }

    public function test_update_changes_fields_and_expiry(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $document = Document::factory()->for($vehicle)->create([
            'name' => 'Seguro 2026', 'expires_on' => '2026-12-31',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/documents/{$document->id}", [
            'kind' => 'insurance',
            'name' => 'Seguro 2026 (refinanciado)',
            'expires_on' => '2026-07-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Seguro 2026 (refinanciado)')
            ->assertJsonPath('data.status', 'expiring')
            ->assertJsonPath('data.days_left', 16);
    }

    public function test_owner_can_delete_document(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $document = Document::factory()->for($vehicle)->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/documents/{$document->id}")->assertOk();
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }

    public function test_foreign_document_is_forbidden(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/documents/{$document->id}")->assertForbidden();
        $this->patchJson("/api/documents/{$document->id}", [
            'kind' => 'other', 'name' => 'X',
        ])->assertForbidden();
        $this->deleteJson("/api/documents/{$document->id}")->assertForbidden();
    }

    public function test_plain_member_cannot_manage_documents(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();
        $document = Document::factory()->for($vehicle)->create();

        Sanctum::actingAs($member);

        $this->postJson("/api/vehicles/{$vehicle->id}/documents", [
            'kind' => 'tax', 'name' => 'Impuesto',
        ])->assertForbidden();

        $this->deleteJson("/api/documents/{$document->id}")->assertForbidden();
    }

    public function test_member_can_view_organization_documents(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();
        Document::factory()->for($vehicle)->create();

        Sanctum::actingAs($member);

        $this->getJson("/api/vehicles/{$vehicle->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_expiry_must_not_precede_issue_date(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/documents", [
            'kind' => 'inspection',
            'name' => 'Revisión',
            'issued_on' => '2026-06-01',
            'expires_on' => '2026-05-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expires_on');
    }

    public function test_document_without_issue_date_is_valid(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/documents", [
            'kind' => 'other',
            'name' => 'Título de propiedad',
            'expires_on' => '2030-01-01',
        ])->assertCreated();
    }

    public function test_kind_must_be_known(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/documents", [
            'kind' => 'inventado', 'name' => 'X',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kind');
    }

    public function test_user_can_attach_and_replace_the_file(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $document = Document::factory()->for($vehicle)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/documents/{$document->id}/file", [
            'file' => UploadedFile::fake()->create('poliza.pdf', 200, 'application/pdf'),
        ])
            ->assertOk()
            ->assertJsonPath('data.file.file_name', 'poliza.pdf');

        $this->postJson("/api/documents/{$document->id}/file", [
            'file' => UploadedFile::fake()->image('foto.png'),
        ])
            ->assertOk()
            ->assertJsonPath('data.file.file_name', 'foto.png');

        // Single file: el anterior se reemplaza.
        $this->assertCount(1, $document->fresh()->getMedia('file'));
    }

    public function test_file_type_is_restricted(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $document = Document::factory()->for($vehicle)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/documents/{$document->id}/file", [
            'file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_user_can_remove_the_file(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $document = Document::factory()->for($vehicle)->create();
        $document->addMedia(UploadedFile::fake()->image('foto.png'))->toMediaCollection('file');

        Sanctum::actingAs($user);

        $this->deleteJson("/api/documents/{$document->id}/file")
            ->assertOk()
            ->assertJsonPath('data.file', null);

        $this->assertCount(0, $document->fresh()->getMedia('file'));
    }

    public function test_summary_endpoint_returns_counts(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Document::factory()->for($vehicle)->create();
        Document::factory()->for($vehicle)->expired()->create();
        Document::factory()->for($vehicle)->expiring()->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/vehicles/{$vehicle->id}/documents-summary")
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.valid', 1)
            ->assertJsonPath('data.expiring', 1)
            ->assertJsonPath('data.expired', 1)
            ->assertJsonPath('data.next_expiration.days_left', 10);
    }
}

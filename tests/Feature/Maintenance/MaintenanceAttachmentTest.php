<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_owner_can_upload_attachment(): void
    {
        $user = User::factory()->create();
        $record = MaintenanceRecord::factory()->for(Vehicle::factory()->forUser($user))->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/maintenance-records/{$record->id}/attachments", [
            'file' => UploadedFile::fake()->image('factura.jpg'),
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.attachments');

        $this->assertSame(1, $record->fresh()->getMedia('attachments')->count());
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $record = MaintenanceRecord::factory()->for(Vehicle::factory()->forUser($user))->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/maintenance-records/{$record->id}/attachments", [
            'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_plain_member_cannot_upload_attachment(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);
        $record = MaintenanceRecord::factory()->for(Vehicle::factory()->forOrganization($organization))->create();

        Sanctum::actingAs($member);

        $this->postJson("/api/maintenance-records/{$record->id}/attachments", [
            'file' => UploadedFile::fake()->image('factura.jpg'),
        ])->assertForbidden();
    }

    public function test_owner_can_delete_attachment(): void
    {
        $user = User::factory()->create();
        $record = MaintenanceRecord::factory()->for(Vehicle::factory()->forUser($user))->create();
        $media = $record->addMedia(UploadedFile::fake()->image('factura.jpg'))->toMediaCollection('attachments');

        Sanctum::actingAs($user);

        $this->deleteJson("/api/maintenance-records/{$record->id}/attachments/{$media->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data.attachments');

        $this->assertSame(0, $record->fresh()->getMedia('attachments')->count());
    }

    public function test_cannot_delete_media_from_another_record(): void
    {
        $user = User::factory()->create();
        $record = MaintenanceRecord::factory()->for(Vehicle::factory()->forUser($user))->create();
        $other = MaintenanceRecord::factory()->for(Vehicle::factory()->forUser($user))->create();
        $media = $other->addMedia(UploadedFile::fake()->image('otra.jpg'))->toMediaCollection('attachments');

        Sanctum::actingAs($user);

        $this->deleteJson("/api/maintenance-records/{$record->id}/attachments/{$media->id}")
            ->assertNotFound();
    }
}

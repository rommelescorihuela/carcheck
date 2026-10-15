<?php

namespace Tests\Feature\Vehicles;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehiclePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_owner_can_upload_photo(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/photo", [
            'photo' => UploadedFile::fake()->image('carro.jpg', 200, 150),
        ])
            ->assertOk()
            ->assertJsonPath('data.photo_url', fn ($url) => is_string($url) && $url !== '');

        $this->assertSame(1, $vehicle->fresh()->media()->count());
    }

    public function test_uploading_again_replaces_the_photo(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/photo", [
            'photo' => UploadedFile::fake()->image('uno.jpg'),
        ])->assertOk();

        $this->postJson("/api/vehicles/{$vehicle->id}/photo", [
            'photo' => UploadedFile::fake()->image('dos.jpg'),
        ])->assertOk();

        $this->assertSame(1, $vehicle->fresh()->media()->count());
    }

    public function test_invalid_file_is_rejected(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/vehicles/{$vehicle->id}/photo", [
            'photo' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photo');
    }

    public function test_plain_member_cannot_upload_to_organization_vehicle(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs($member);

        $this->postJson("/api/vehicles/{$vehicle->id}/photo", [
            'photo' => UploadedFile::fake()->image('carro.jpg'),
        ])->assertForbidden();
    }

    public function test_admin_can_upload_to_organization_vehicle(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $organization->members()->attach($admin, ['role' => 'admin', 'joined_at' => now()]);
        $vehicle = Vehicle::factory()->forOrganization($organization)->create();

        Sanctum::actingAs($admin);

        $this->postJson("/api/vehicles/{$vehicle->id}/photo", [
            'photo' => UploadedFile::fake()->image('carro.jpg'),
        ])->assertOk();

        $this->assertSame(1, $vehicle->fresh()->media()->count());
    }

    public function test_owner_can_delete_photo(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->forUser($user)->create();
        $vehicle->addMedia(UploadedFile::fake()->image('carro.jpg'))->toMediaCollection('photo');

        Sanctum::actingAs($user);

        $this->deleteJson("/api/vehicles/{$vehicle->id}/photo")
            ->assertOk()
            ->assertJsonPath('data.photo_url', null);

        $this->assertSame(0, $vehicle->fresh()->media()->count());
    }
}

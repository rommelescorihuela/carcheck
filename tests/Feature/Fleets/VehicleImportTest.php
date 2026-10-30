<?php

namespace Tests\Feature\Fleets;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_vehicles_from_a_semicolon_csv_with_accents(): void
    {
        [$organization, $owner] = $this->fleet();

        Sanctum::actingAs($owner);

        $csv = "\xEF\xBB\xBFmarca;modelo;año;placa;kilometraje;combustible;tipo\n"
            ."Toyota;Corolla;2020;ABC123;45000;gasolina;auto\n"
            ."Ford;Ranger;2019;XYZ987;78000;diesel;camioneta\n";

        $response = $this->post('/api/vehicles/import', [
            'organization_id' => $organization->id,
            'file' => UploadedFile::fake()->createWithContent('flota.csv', $csv),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.failed', 0);

        $this->assertSame(2, $organization->vehicles()->count());

        $this->assertDatabaseHas('vehicles', [
            'owner_id' => $organization->id,
            'plate' => 'ABC123',
            'fuel_type' => 'gasoline',
            'type' => 'car',
        ]);

        $this->assertDatabaseHas('vehicles', [
            'plate' => 'XYZ987',
            'fuel_type' => 'diesel',
            'type' => 'truck',
        ]);
    }

    public function test_import_stops_at_the_plan_limit_and_explains_why(): void
    {
        [$organization, $owner] = $this->fleet(maxVehicles: 3);

        Sanctum::actingAs($owner);

        $csv = "marca,modelo\nToyota,Corolla\nFord,Ranger\n";

        $this->post('/api/vehicles/import', [
            'organization_id' => $organization->id,
            'file' => UploadedFile::fake()->createWithContent('flota.csv', $csv),
        ])
            ->assertOk()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.remaining', 1);

        $this->post('/api/vehicles/import', [
            'organization_id' => $organization->id,
            'file' => UploadedFile::fake()->createWithContent('flota.csv', $csv),
        ])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.remaining', 0);

        $this->assertSame(3, $organization->vehicles()->count());
    }

    public function test_import_reports_invalid_rows_without_aborting_the_file(): void
    {
        [$organization, $owner] = $this->fleet();

        Sanctum::actingAs($owner);

        $csv = "marca,modelo,año\nToyota,Corolla,2020\n,Sin marca,2018\nFord,Ranger,1800\n";

        $this->post('/api/vehicles/import', [
            'organization_id' => $organization->id,
            'file' => UploadedFile::fake()->createWithContent('flota.csv', $csv),
        ])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.failed', 2)
            ->assertJsonCount(2, 'data.errors');
    }

    public function test_import_requires_make_and_model_columns(): void
    {
        [$organization, $owner] = $this->fleet();

        Sanctum::actingAs($owner);

        $this->post('/api/vehicles/import', [
            'organization_id' => $organization->id,
            'file' => UploadedFile::fake()->createWithContent('flota.csv', "color,año\nRojo,2020\n"),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_driver_cannot_import_vehicles(): void
    {
        [$organization] = $this->fleet();

        $driver = User::factory()->create();
        $organization->members()->attach($driver->id, ['role' => 'driver', 'joined_at' => now()]);

        Sanctum::actingAs($driver);

        $this->post('/api/vehicles/import', [
            'organization_id' => $organization->id,
            'file' => UploadedFile::fake()->createWithContent('flota.csv', "marca,modelo\nToyota,Corolla\n"),
        ])->assertForbidden();
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function fleet(int $maxVehicles = 10): array
    {
        $organization = Organization::factory()->create();
        $plan = Plan::factory()->create(['max_vehicles' => $maxVehicles, 'max_members' => 10]);

        Subscription::factory()->forOrganization($organization)->create(['plan_id' => $plan->id]);

        return [$organization, $organization->owner];
    }
}

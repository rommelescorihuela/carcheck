<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_global_and_scope_types(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->attach($user, ['role' => 'member', 'joined_at' => now()]);

        $global = MaintenanceType::factory()->create();
        $own = MaintenanceType::factory()->forUser($user)->create();
        $orgType = MaintenanceType::factory()->forOrganization($organization)->create();
        $foreign = MaintenanceType::factory()->forUser(User::factory()->create())->create();

        Sanctum::actingAs($user);

        $ids = collect($this->getJson('/api/maintenance-types')->assertOk()->json('data'))
            ->pluck('id');

        $this->assertTrue($ids->contains($global->id));
        $this->assertTrue($ids->contains($own->id));
        $this->assertTrue($ids->contains($orgType->id));
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_user_can_create_personal_type(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/maintenance-types', ['name' => 'Cambio de bujías'])
            ->assertCreated()
            ->assertJsonPath('data.owner_type', User::class)
            ->assertJsonPath('data.is_global', false);

        $this->assertDatabaseHas('maintenance_types', [
            'name' => 'Cambio de bujías',
            'owner_id' => $user->id,
        ]);
    }

    public function test_owner_can_create_organization_type(): void
    {
        $organization = Organization::factory()->create();
        Sanctum::actingAs($organization->owner);

        $this->postJson('/api/maintenance-types', [
            'name' => 'Servicio de flota',
            'organization_id' => $organization->id,
        ])->assertCreated()->assertJsonPath('data.owner_id', $organization->id);
    }

    public function test_plain_member_cannot_create_organization_type(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($member);

        $this->postJson('/api/maintenance-types', [
            'name' => 'Servicio de flota',
            'organization_id' => $organization->id,
        ])->assertForbidden();
    }

    public function test_custom_type_can_be_updated_but_global_cannot(): void
    {
        $user = User::factory()->create();
        $own = MaintenanceType::factory()->forUser($user)->create();
        $global = MaintenanceType::factory()->create();

        Sanctum::actingAs($user);

        $this->patchJson("/api/maintenance-types/{$own->id}", ['name' => 'Actualizado'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Actualizado');

        $this->patchJson("/api/maintenance-types/{$global->id}", ['name' => 'X'])
            ->assertForbidden();
    }

    public function test_foreign_type_cannot_be_updated(): void
    {
        $user = User::factory()->create();
        $foreign = MaintenanceType::factory()->forUser(User::factory()->create())->create();

        Sanctum::actingAs($user);

        $this->patchJson("/api/maintenance-types/{$foreign->id}", ['name' => 'X'])
            ->assertForbidden();
    }

    public function test_custom_type_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $own = MaintenanceType::factory()->forUser($user)->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/maintenance-types/{$own->id}")->assertOk();
        $this->assertDatabaseMissing('maintenance_types', ['id' => $own->id]);
    }

    public function test_type_in_use_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $own = MaintenanceType::factory()->forUser($user)->create();
        MaintenanceRecord::factory()->create(['maintenance_type_id' => $own->id]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/maintenance-types/{$own->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }
}

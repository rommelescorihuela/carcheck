<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_owned_organizations_can_delete_account(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->attach($user, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/account', ['password' => 'password'])->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('organization_user', ['user_id' => $user->id]);
    }

    public function test_owner_cannot_delete_account_until_transferring_organizations(): void
    {
        $organization = Organization::factory()->create();
        $owner = $organization->owner;

        Sanctum::actingAs($owner);

        $this->deleteJson('/api/account', ['password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('account');

        $this->assertNotSoftDeleted('users', ['id' => $owner->id]);
    }

    public function test_token_is_revoked_after_account_deletion(): void
    {
        $token = $this->postJson('/api/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'terms' => true,
        ])->json('access_token');

        $this->withToken($token)->deleteJson('/api/account', ['password' => 'secret-password'])->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_account_deletion_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/account', ['password' => 'incorrecta'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_deleted_email_can_be_used_to_register_again(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'password' => 'secret-password',
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/account', ['password' => 'secret-password'])->assertOk();

        $this->assertDatabaseHas('users', ['email' => 'deleted-'.$user->id.'@users.invalid']);

        $this->postJson('/api/register', [
            'name' => 'Ana de nuevo',
            'email' => 'ana@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'terms' => true,
        ])->assertCreated();
    }

    public function test_owner_can_delete_account_after_transferring_ownership(): void
    {
        $organization = Organization::factory()->create();
        $owner = $organization->owner;
        $target = User::factory()->create();
        $organization->members()->attach($target, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/organizations/{$organization->id}/transfer", ['user_id' => $target->id])
            ->assertOk();

        $this->deleteJson('/api/account', ['password' => 'password'])->assertOk();

        $this->assertSoftDeleted('users', ['id' => $owner->id]);
    }

    public function test_owner_can_delete_account_after_deleting_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $owner = $organization->owner;

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/organizations/{$organization->id}")->assertOk();

        $this->deleteJson('/api/account', ['password' => 'password'])->assertOk();

        $this->assertSoftDeleted('users', ['id' => $owner->id]);
    }
}

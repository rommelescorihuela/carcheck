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

        $this->deleteJson('/api/account')->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('organization_user', ['user_id' => $user->id]);
    }

    public function test_owner_cannot_delete_account_until_transferring_organizations(): void
    {
        $organization = Organization::factory()->create();
        $owner = $organization->owner;

        Sanctum::actingAs($owner);

        $this->deleteJson('/api/account')
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
        ])->json('access_token');

        $this->withToken($token)->deleteJson('/api/account')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }
}

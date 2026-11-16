<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_name_email_and_currency(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/user', [
            'name' => 'Ana Pérez',
            'email' => 'ana.nueva@example.com',
            'currency' => 'ves',
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Ana Pérez')
            ->assertJsonPath('user.email', 'ana.nueva@example.com')
            ->assertJsonPath('user.currency', 'VES');
    }

    public function test_user_cannot_take_another_users_email(): void
    {
        User::factory()->create(['email' => 'ocupado@example.com']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/user', ['email' => 'ocupado@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_user_can_change_password_and_other_sessions_are_revoked(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $current = $user->createToken('actual');
        $other = $user->createToken('otra');

        $this->withToken($current->plainTextToken)
            ->putJson('/api/user/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        Sanctum::actingAs($user);

        $this->putJson('/api/user/password', [
            'current_password' => 'incorrecta',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    public function test_registration_requires_accepting_terms(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('terms');
    }

    public function test_registration_records_terms_acceptance(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'terms' => true,
        ])->assertCreated();

        $this->assertNotNull(User::firstWhere('email', 'ana@example.com')->terms_accepted_at);
    }
}

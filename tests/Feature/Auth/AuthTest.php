<?php

namespace Tests\Feature\Auth;

use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ], $overrides);
    }

    public function test_user_can_register(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/register', $this->registerPayload());

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'access_token', 'token_type'])
            ->assertJsonPath('token_type', 'Bearer');

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com']);
        Mail::assertQueued(WelcomeEmail::class);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/register', $this->registerPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_register_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/register', $this->registerPayload(['password_confirmation' => 'otra-cosa']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'ana@example.com',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/login', [
            'email' => 'ana@example.com',
            'password' => 'secret-password',
        ])
            ->assertOk()
            ->assertJsonStructure(['user', 'access_token', 'token_type']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/login', [
            'email' => 'ana@example.com',
            'password' => 'incorrecta',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_authenticated_user_can_be_retrieved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_user_can_logout_and_token_is_revoked(): void
    {
        $token = $this->postJson('/api/register', $this->registerPayload())
            ->json('access_token');

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_user_can_refresh_token(): void
    {
        $oldToken = $this->postJson('/api/register', $this->registerPayload())
            ->json('access_token');

        $newToken = $this->withToken($oldToken)
            ->postJson('/api/token/refresh')
            ->assertOk()
            ->json('access_token');

        $this->assertNotSame($oldToken, $newToken);
        $this->app['auth']->forgetGuards();
        $this->withToken($oldToken)->getJson('/api/user')->assertUnauthorized();
        $this->withToken($newToken)->getJson('/api/user')->assertOk();
    }
}

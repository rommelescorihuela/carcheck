<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_the_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'ana@example.com'])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_does_not_reveal_unknown_emails(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', ['email' => 'nadie@example.com'])
            ->assertOk();

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'password' => 'old-password',
        ]);
        $user->createToken('otra-sesion');

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'ana@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_reset_password_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/reset-password', [
            'token' => 'token-invalido',
            'email' => 'ana@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}

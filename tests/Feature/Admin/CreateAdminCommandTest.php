<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_super_admin_with_a_password(): void
    {
        $this->artisan('admin:create', ['email' => 'jefa@carcheck.test', '--name' => 'Jefa'])
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'secreto-largo')
            ->expectsQuestion('Repite la contraseña', 'secreto-largo')
            ->assertSuccessful();

        $user = User::firstWhere('email', 'jefa@carcheck.test');

        $this->assertNotNull($user);
        $this->assertSame('Jefa', $user->name);
        $this->assertTrue(Hash::check('secreto-largo', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertTrue(Role::where('name', 'super_admin')->exists());
    }

    public function test_promotes_an_existing_user_without_touching_the_password(): void
    {
        $user = User::factory()->create(['email' => 'admin@carcheck.test']);
        $original = $user->password;

        $this->artisan('admin:create', ['email' => 'admin@carcheck.test'])->assertSuccessful();

        $user->refresh();

        $this->assertSame($original, $user->password);
        $this->assertTrue($user->hasRole('super_admin'));
    }

    public function test_restores_a_deleted_account(): void
    {
        $user = User::factory()->create(['email' => 'vuelve@carcheck.test']);
        $user->delete();

        $this->artisan('admin:create', ['email' => 'vuelve@carcheck.test'])->assertSuccessful();

        $this->assertFalse($user->fresh()->trashed());
        $this->assertTrue($user->fresh()->hasRole('super_admin'));
    }

    public function test_rejects_a_short_or_mismatched_password(): void
    {
        $this->artisan('admin:create', ['email' => 'corta@carcheck.test'])
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'corta')
            ->expectsQuestion('Repite la contraseña', 'corta')
            ->assertFailed();

        $this->assertNull(User::firstWhere('email', 'corta@carcheck.test'));

        $this->artisan('admin:create', ['email' => 'distinta@carcheck.test'])
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'secreto-largo')
            ->expectsQuestion('Repite la contraseña', 'otro-secreto')
            ->assertFailed();

        $this->assertNull(User::firstWhere('email', 'distinta@carcheck.test'));
    }
}

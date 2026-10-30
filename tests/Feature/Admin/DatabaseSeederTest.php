<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_platform_admin_from_env(): void
    {
        $this->withEnv([
            'ADMIN_EMAIL' => 'dueno@carcheck.test',
            'ADMIN_PASSWORD' => 'clave-segura-123',
            'ADMIN_NAME' => 'Dueño de Plataforma',
        ], fn () => $this->seed());

        $admin = User::query()->where('email', 'dueno@carcheck.test')->firstOrFail();

        $this->assertSame('Dueño de Plataforma', $admin->name);
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue(Hash::check('clave-segura-123', $admin->password));
    }

    /**
     * @param  array<string, string>  $values
     */
    private function withEnv(array $values, callable $callback): void
    {
        $previous = [];

        foreach ($values as $key => $value) {
            $previous[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        try {
            $callback();
        } finally {
            foreach ($previous as $key => [$env, $server]) {
                if ($env === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $env;
                }

                if ($server === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $server;
                }
            }
        }
    }
}

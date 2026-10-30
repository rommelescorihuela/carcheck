<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * En producción solo crea datos de plataforma y, si se define `ADMIN_EMAIL` +
     * `ADMIN_PASSWORD` en el `.env`, el primer `super_admin`. Los usuarios demo
     * (`admin@carcheck.test`, `demo@carcheck.test`) quedan limitados a local y tests:
     * ejecutar `migrate --seed` en un servidor no debe abrir una puerta trasera.
     */
    public function run(): void
    {
        $this->call(RolesSeeder::class);
        $this->call(MaintenanceTypeSeeder::class);
        $this->call(PlanSeeder::class);

        $this->createPlatformAdmin();

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $admin = User::factory()->create([
            'name' => 'Admin CarCheck',
            'email' => 'admin@carcheck.test',
        ]);
        $admin->assignRole('super_admin');

        User::factory()->create([
            'name' => 'Usuario Demo',
            'email' => 'demo@carcheck.test',
        ]);
    }

    /**
     * Administrador de plataforma para instalaciones sin Terminal (cPanel + cron).
     * Las variables se borran del `.env` después de la instalación.
     */
    private function createPlatformAdmin(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (blank($email) || blank($password)) {
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrador'),
                'password' => (string) $password,
                'email_verified_at' => now(),
            ],
        );

        $admin->assignRole('super_admin');
    }
}

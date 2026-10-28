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
     */
    public function run(): void
    {
        $this->call(RolesSeeder::class);
        $this->call(MaintenanceTypeSeeder::class);
        $this->call(PlanSeeder::class);

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
}

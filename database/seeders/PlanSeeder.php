<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'basico',
                'name' => 'Básico',
                'description' => 'Un vehículo y su historial completo.',
                'price' => 3.99,
                'trial_days' => 14,
                'max_vehicles' => 1,
                'max_members' => null,
                'sort' => 1,
            ],
            [
                'code' => 'familiar',
                'name' => 'Familiar',
                'description' => 'Hasta 5 vehículos compartidos con tu organización.',
                'price' => 7.99,
                'trial_days' => 14,
                'max_vehicles' => 5,
                'max_members' => 5,
                'sort' => 2,
            ],
            [
                'code' => 'flota',
                'name' => 'Flota',
                'description' => 'Hasta 25 vehículos y 10 personas, con conductores y reportes.',
                'price' => 29.99,
                'trial_days' => 14,
                'max_vehicles' => 25,
                'max_members' => 10,
                'sort' => 3,
            ],
            [
                'code' => 'empresa',
                'name' => 'Empresa',
                'description' => 'Hasta 75 vehículos y 25 personas para operaciones grandes.',
                'price' => 59.99,
                'trial_days' => 14,
                'max_vehicles' => 75,
                'max_members' => 25,
                'sort' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['code' => $plan['code']],
                $plan + ['currency' => 'USD', 'interval' => 'monthly', 'is_active' => true],
            );
        }
    }
}

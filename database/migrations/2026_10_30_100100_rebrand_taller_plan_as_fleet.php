<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El plan "Taller" era un gestor de vehículos, no un taller mecánico: pasa a ser "Flota"
 * (equipos con conductores) y se añade "Empresa" para flotas grandes. Los límites dejan de ser
 * informativos: `max_vehicles` y `max_members` bloquean altas en la API.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Se renombra en sitio para conservar el `plan_id` de las suscripciones existentes.
        DB::table('plans')->where('code', 'taller')->update([
            'code' => 'flota',
            'name' => 'Flota',
            'description' => 'Hasta 25 vehículos y 10 personas, con conductores y reportes.',
            'price' => 29.99,
            'max_vehicles' => 25,
            'max_members' => 10,
            'sort' => 3,
            'updated_at' => $now,
        ]);

        if (! DB::table('plans')->where('code', 'empresa')->exists()) {
            DB::table('plans')->insert([
                'code' => 'empresa',
                'name' => 'Empresa',
                'description' => 'Hasta 75 vehículos y 25 personas para operaciones grandes.',
                'price' => 59.99,
                'currency' => 'USD',
                'interval' => 'monthly',
                'trial_days' => 14,
                'max_vehicles' => 75,
                'max_members' => 25,
                'is_active' => true,
                'sort' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('plans')->where('code', 'empresa')->delete();

        DB::table('plans')->where('code', 'flota')->update([
            'code' => 'taller',
            'name' => 'Taller',
            'description' => 'Vehículos y miembros sin límite para talleres.',
            'price' => 19.99,
            'max_vehicles' => null,
            'max_members' => null,
            'sort' => 3,
            'updated_at' => now(),
        ]);
    }
};

<?php

namespace Database\Seeders;

use App\Models\MaintenanceType;
use Illuminate\Database\Seeder;

class MaintenanceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Cambio de aceite', 'interval_km' => 5000, 'interval_months' => 6],
            ['name' => 'Filtros', 'interval_km' => 10000, 'interval_months' => 12],
            ['name' => 'Frenos', 'interval_km' => 30000, 'interval_months' => null],
            ['name' => 'Neumáticos', 'interval_km' => 40000, 'interval_months' => null],
            ['name' => 'Batería', 'interval_km' => null, 'interval_months' => 36],
            ['name' => 'Revisión general', 'interval_km' => 15000, 'interval_months' => 12],
            ['name' => 'Alineación y balanceo', 'interval_km' => 10000, 'interval_months' => null],
            ['name' => 'Líquido refrigerante', 'interval_km' => 40000, 'interval_months' => 24],
            ['name' => 'Correa de distribución', 'interval_km' => 60000, 'interval_months' => null],
        ];

        foreach ($types as $type) {
            MaintenanceType::firstOrCreate(
                ['name' => $type['name'], 'owner_type' => null, 'owner_id' => null],
                $type,
            );
        }
    }
}

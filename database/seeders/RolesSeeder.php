<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super_admin', 'panel_user'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}

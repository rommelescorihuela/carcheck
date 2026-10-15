<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_seeder_creates_platform_roles(): void
    {
        $this->seed(RolesSeeder::class);

        $this->assertTrue(Role::where('name', 'super_admin')->exists());
        $this->assertTrue(Role::where('name', 'panel_user')->exists());
    }

    public function test_super_admin_can_access_panel_and_resources(): void
    {
        $this->seed(RolesSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)->get('/admin')->assertSuccessful();
        $this->actingAs($admin)->get('/admin/organizations')->assertSuccessful();
        $this->actingAs($admin)->get('/admin/users')->assertSuccessful();
    }

    public function test_regular_user_cannot_access_panel(): void
    {
        $this->seed(RolesSeeder::class);

        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }
}

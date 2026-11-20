<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz es la landing de producto, no la pantalla de bienvenida de Laravel
     * ni un salto directo al panel.
     */
    public function test_the_root_shows_the_product_landing(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('CarCheck')
            ->assertSee('El auto avisa antes de romperse');
    }

    public function test_the_admin_panel_is_still_reachable(): void
    {
        $this->get('/admin/login')->assertOk();
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * El backend se publica en un subdominio de API: la raíz no debe mostrar
     * la pantalla de bienvenida de Laravel.
     */
    public function test_the_root_redirects_to_the_admin_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }
}

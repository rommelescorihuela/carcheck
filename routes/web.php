<?php

use Illuminate\Support\Facades\Route;

/*
 * El backend vive en un subdominio propio (api.tudominio.com) y su única
 * interfaz web es el panel. La raíz lleva al panel en vez de mostrar la
 * pantalla de bienvenida de Laravel.
 */
Route::redirect('/', '/admin');

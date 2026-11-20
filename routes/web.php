<?php

use Illuminate\Support\Facades\Route;

/*
 * El backend vive en un subdominio propio (api.tudominio.com). La raíz es la
 * landing de producto; el panel sigue en /admin.
 */
Route::view('/', 'landing')->name('home');

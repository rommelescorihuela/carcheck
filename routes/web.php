<?php

use App\Http\Controllers\AccessRequestController;
use Illuminate\Support\Facades\Route;

/*
 * El backend vive en un subdominio propio (api.tudominio.com). La raíz es la
 * landing de producto; el panel sigue en /admin.
 */
Route::view('/', 'landing')->name('home');

Route::post('/solicitar-acceso', [AccessRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('access-request.store');

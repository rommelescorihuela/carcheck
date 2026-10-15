<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/token/refresh', [AuthController::class, 'refreshToken'])->middleware('throttle:30,1');

    Route::delete('/account', [AccountController::class, 'destroy']);

    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::post('/organizations', [OrganizationController::class, 'store']);
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);

    Route::get('/organizations/{organization}/members', [MemberController::class, 'index']);
    Route::patch('/organizations/{organization}/members/{user}', [MemberController::class, 'update']);
    Route::delete('/organizations/{organization}/members/{user}', [MemberController::class, 'destroy']);

    Route::post('/organizations/{organization}/invitations', [InvitationController::class, 'store'])
        ->middleware('throttle:20,1');
    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);

    Route::apiResource('vehicles', VehicleController::class);
    Route::post('/vehicles/{vehicle}/photo', [VehicleController::class, 'storePhoto']);
    Route::delete('/vehicles/{vehicle}/photo', [VehicleController::class, 'destroyPhoto']);
    Route::post('/vehicles/{vehicle}/transfer', [VehicleController::class, 'transfer']);
});

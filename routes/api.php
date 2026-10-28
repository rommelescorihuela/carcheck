<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FuelEntryController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\MaintenancePlanController;
use App\Http\Controllers\Api\MaintenanceRecordController;
use App\Http\Controllers\Api\MaintenanceTypeController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\ReportController;
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

    Route::get('/maintenance-types', [MaintenanceTypeController::class, 'index']);
    Route::post('/maintenance-types', [MaintenanceTypeController::class, 'store']);
    Route::patch('/maintenance-types/{type}', [MaintenanceTypeController::class, 'update']);
    Route::delete('/maintenance-types/{type}', [MaintenanceTypeController::class, 'destroy']);

    Route::get('/vehicles/{vehicle}/maintenance-plans', [MaintenancePlanController::class, 'index']);
    Route::put('/vehicles/{vehicle}/maintenance-plans/{type}', [MaintenancePlanController::class, 'update']);
    Route::delete('/vehicles/{vehicle}/maintenance-plans/{type}', [MaintenancePlanController::class, 'destroy']);

    Route::get('/vehicles/{vehicle}/maintenance-records', [MaintenanceRecordController::class, 'index']);
    Route::post('/vehicles/{vehicle}/maintenance-records', [MaintenanceRecordController::class, 'store']);
    Route::get('/vehicles/{vehicle}/maintenance-due', [MaintenanceRecordController::class, 'due']);
    Route::get('/maintenance-records/{record}', [MaintenanceRecordController::class, 'show']);
    Route::patch('/maintenance-records/{record}', [MaintenanceRecordController::class, 'update']);
    Route::delete('/maintenance-records/{record}', [MaintenanceRecordController::class, 'destroy']);
    Route::post('/maintenance-records/{record}/attachments', [MaintenanceRecordController::class, 'storeAttachment']);
    Route::delete('/maintenance-records/{record}/attachments/{media}', [MaintenanceRecordController::class, 'destroyAttachment']);

    Route::get('/vehicles/{vehicle}/fuel-entries', [FuelEntryController::class, 'index']);
    Route::post('/vehicles/{vehicle}/fuel-entries', [FuelEntryController::class, 'store']);
    Route::get('/vehicles/{vehicle}/fuel-summary', [FuelEntryController::class, 'summary']);
    Route::get('/fuel-entries/{entry}', [FuelEntryController::class, 'show']);
    Route::patch('/fuel-entries/{entry}', [FuelEntryController::class, 'update']);
    Route::delete('/fuel-entries/{entry}', [FuelEntryController::class, 'destroy']);

    Route::get('/vehicles/{vehicle}/documents', [DocumentController::class, 'index']);
    Route::post('/vehicles/{vehicle}/documents', [DocumentController::class, 'store']);
    Route::get('/vehicles/{vehicle}/documents-summary', [DocumentController::class, 'summary']);
    Route::get('/documents/{document}', [DocumentController::class, 'show']);
    Route::patch('/documents/{document}', [DocumentController::class, 'update']);
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
    Route::post('/documents/{document}/file', [DocumentController::class, 'storeFile']);
    Route::delete('/documents/{document}/file', [DocumentController::class, 'destroyFile']);

    Route::get('/billing/plans', [BillingController::class, 'plans']);
    Route::get('/billing/subscription', [BillingController::class, 'show']);
    Route::post('/billing/subscription', [BillingController::class, 'store']);
    Route::patch('/billing/subscription', [BillingController::class, 'update']);
    Route::delete('/billing/subscription', [BillingController::class, 'destroy']);

    Route::get('/billing/payments', [PaymentController::class, 'index']);
    Route::post('/billing/payments', [PaymentController::class, 'store']);
    Route::post('/billing/payments/{payment}/proof', [PaymentController::class, 'storeProof']);
    Route::delete('/billing/payments/{payment}/proof', [PaymentController::class, 'destroyProof']);
    Route::post('/billing/payments/{payment}/approve', [PaymentController::class, 'approve']);
    Route::post('/billing/payments/{payment}/reject', [PaymentController::class, 'reject']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences']);
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);
    Route::get('/notifications/push/public-key', [NotificationController::class, 'publicKey']);
    Route::post('/notifications/push-subscriptions', [NotificationController::class, 'storeSubscription']);
    Route::delete('/notifications/push-subscriptions', [NotificationController::class, 'destroySubscription']);
    Route::post('/notifications/test', [NotificationController::class, 'sendTest']);
    // Las rutas con `{notification}` van al final para no capturar los segmentos fijos.
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::post('/reminders/sync', [ReminderController::class, 'sync']);
    Route::post('/reminders/{reminder}/dismiss', [ReminderController::class, 'dismiss']);

    Route::get('/reports/expenses', [ReportController::class, 'expenses']);
});

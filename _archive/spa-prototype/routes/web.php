<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppShellController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BootstrapController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecordController;
use Illuminate\Support\Facades\Route;

// E-IPCR single-page application shell.
Route::get('/', AppShellController::class)->name('app');
Route::get('/login', fn () => redirect('/'))->name('login');

// Session authentication (JSON endpoints consumed by the SPA).
Route::post('/api/login', [AuthController::class, 'login']);
Route::get('/api/login-accounts', [AuthController::class, 'accounts']);

Route::middleware('auth')->group(function () {
    Route::post('/api/logout', [AuthController::class, 'logout']);
    Route::get('/api/bootstrap', BootstrapController::class);

    Route::post('/api/records', [RecordController::class, 'store']);
    Route::put('/api/records/{record}', [RecordController::class, 'update']);
    Route::post('/api/records/{record}/transition', [RecordController::class, 'transition']);

    Route::put('/api/profile', [ProfileController::class, 'update']);
    Route::post('/api/notifications/read-all', [NotificationController::class, 'readAll']);

    // Administrator configuration.
    Route::post('/api/admin/users', [AdminController::class, 'storeUser']);
    Route::put('/api/admin/users/{id}', [AdminController::class, 'updateUser']);
    Route::post('/api/admin/users/{id}/toggle', [AdminController::class, 'toggleUser']);
    Route::post('/api/admin/units', [AdminController::class, 'storeUnit']);
    Route::put('/api/admin/units/{id}', [AdminController::class, 'updateUnit']);
    Route::post('/api/admin/periods', [AdminController::class, 'storePeriod']);
    Route::put('/api/admin/periods/{id}', [AdminController::class, 'updatePeriod']);
    Route::post('/api/admin/periods/{id}/activate', [AdminController::class, 'activatePeriod']);
    Route::put('/api/admin/settings', [AdminController::class, 'updateSettings']);
    Route::post('/api/admin/reset', [AdminController::class, 'reset']);
});

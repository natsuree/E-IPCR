<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employee\IpcrController as EmployeeIpcrController;
use App\Http\Controllers\Hr\RatingPeriodController;
use App\Http\Controllers\Hr\RatingSettingsController;
use App\Http\Controllers\Hr\ReportController as HrReportController;
use App\Http\Controllers\Hr\TemplateController as HrTemplateController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Review\AnalyticsController;
use App\Http\Controllers\Review\IpcrViewController;
use App\Http\Controllers\Review\QueueController;
use App\Http\Controllers\Review\RecordController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');

    Route::get('/forgot-password', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Sends each role to its own dashboard.
    Route::get('/', fn () => redirect(auth()->user()->dashboardRoute()))->name('home');
});

/** Pages every role has: dashboard, notifications and profile. */
$common = function () {
    Route::get('/', [DashboardController::class, 'show'])->name('dashboard');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::get('/notifications/{notification}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
};

/** Review queue, review screen and IPCR records shared by the three reviewer roles. */
$reviewer = function () {
    Route::get('/queue', [QueueController::class, 'index'])->name('queue');
    Route::get('/review/{ipcr}', [QueueController::class, 'show'])->name('review');
    Route::post('/review/{ipcr}', [QueueController::class, 'decide'])->name('review.decide');
    Route::get('/records', [RecordController::class, 'index'])->name('records');
    Route::get('/records/{ipcr}', [RecordController::class, 'show'])->name('records.show');
    Route::get('/records/{ipcr}/print', [RecordController::class, 'print'])->name('records.print');
    Route::get('/records/{ipcr}/pdf', [RecordController::class, 'pdf'])->name('records.pdf');
    Route::get('/ipcrs/{ipcr}', [IpcrViewController::class, 'show'])->name('ipcrs.show');
};

/** Scoped analytics for the Department Head and the Dean/Director. */
$analytics = function () {
    Route::get('/analytics', [AnalyticsController::class, 'show'])->name('analytics');
    Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
};

Route::middleware(['auth', 'role:teaching,nonteaching'])->prefix('employee')->name('employee.')->group(function () use ($common) {
    $common();
    Route::get('/ipcrs', [EmployeeIpcrController::class, 'index'])->name('ipcrs.index');
    Route::get('/ipcr', [EmployeeIpcrController::class, 'current'])->name('ipcrs.current');
    Route::post('/ipcrs', [EmployeeIpcrController::class, 'store'])->name('ipcrs.store');
    Route::get('/ipcrs/{ipcr}', [EmployeeIpcrController::class, 'show'])->name('ipcrs.show');
    Route::get('/ipcrs/{ipcr}/edit', [EmployeeIpcrController::class, 'edit'])->name('ipcrs.edit');
    Route::put('/ipcrs/{ipcr}', [EmployeeIpcrController::class, 'update'])->name('ipcrs.update');
    Route::get('/ipcrs/{ipcr}/submit', [EmployeeIpcrController::class, 'confirm'])->name('ipcrs.confirm');
    Route::post('/ipcrs/{ipcr}/submit', [EmployeeIpcrController::class, 'submit'])->name('ipcrs.submit');
    Route::get('/ipcrs/{ipcr}/print', [EmployeeIpcrController::class, 'print'])->name('ipcrs.print');
    Route::get('/ipcrs/{ipcr}/pdf', [EmployeeIpcrController::class, 'pdf'])->name('ipcrs.pdf');
    Route::get('/tracking', [EmployeeIpcrController::class, 'tracking'])->name('tracking');
});

Route::middleware(['auth', 'role:depthead'])->prefix('depthead')->name('depthead.')->group(function () use ($common, $reviewer, $analytics) {
    $common();
    $reviewer();
    $analytics();
});

Route::middleware(['auth', 'role:dean'])->prefix('dean')->name('dean.')->group(function () use ($common, $reviewer, $analytics) {
    $common();
    $reviewer();
    $analytics();
});

Route::middleware(['auth', 'role:hr'])->prefix('hr')->name('hr.')->group(function () use ($common, $reviewer) {
    $common();
    $reviewer();

    Route::get('/templates', [HrTemplateController::class, 'index'])->name('templates');
    Route::post('/templates', [HrTemplateController::class, 'store'])->name('templates.store');
    Route::put('/templates/{template}', [HrTemplateController::class, 'update'])->name('templates.update');
    Route::post('/templates/{template}/publish', [HrTemplateController::class, 'publish'])->name('templates.publish');
    Route::post('/templates/{template}/items', [HrTemplateController::class, 'storeItem'])->name('templates.items.store');
    Route::put('/templates/{template}/items/{item}', [HrTemplateController::class, 'updateItem'])->name('templates.items.update')->scopeBindings();
    Route::delete('/templates/{template}/items/{item}', [HrTemplateController::class, 'destroyItem'])->name('templates.items.destroy')->scopeBindings();

    Route::get('/periods', [RatingPeriodController::class, 'index'])->name('periods.index');
    Route::post('/periods', [RatingPeriodController::class, 'store'])->name('periods.store');
    Route::put('/periods/{period}', [RatingPeriodController::class, 'update'])->name('periods.update');
    Route::post('/periods/{period}/open', [RatingPeriodController::class, 'open'])->name('periods.open');
    Route::post('/periods/{period}/close', [RatingPeriodController::class, 'close'])->name('periods.close');

    Route::get('/settings', [RatingSettingsController::class, 'show'])->name('settings');
    Route::put('/settings', [RatingSettingsController::class, 'update'])->name('settings.update');

    Route::get('/reports', [HrReportController::class, 'index'])->name('reports');
    Route::get('/reports/export', [HrReportController::class, 'export'])->name('reports.export');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () use ($common) {
    $common();
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/users/import', [UserImportController::class, 'create'])->name('users.import');
    Route::post('/users/import/preview', [UserImportController::class, 'preview'])->name('users.import.preview');
    Route::post('/users/import', [UserImportController::class, 'store'])->name('users.import.store');
    Route::get('/users/import/template', [UserImportController::class, 'sample'])->name('users.import.sample');
    Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/toggle', [AdminUserController::class, 'toggle'])->name('users.toggle');
    Route::post('/users/{user}/resend', [AdminUserController::class, 'resend'])->name('users.resend');

    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store');
    Route::get('/units/{college}', [UnitController::class, 'edit'])->name('units.edit');
    Route::put('/units/{college}', [UnitController::class, 'update'])->name('units.update');
    Route::post('/units/{college}/toggle', [UnitController::class, 'toggle'])->name('units.toggle');
    Route::post('/units/{college}/departments', [UnitController::class, 'storeDepartment'])->name('units.departments.store');
    Route::put('/units/{college}/departments/{department}', [UnitController::class, 'updateDepartment'])->name('units.departments.update')->scopeBindings();
    Route::post('/units/{college}/departments/{department}/toggle', [UnitController::class, 'toggleDepartment'])->name('units.departments.toggle')->scopeBindings();

    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit');
});

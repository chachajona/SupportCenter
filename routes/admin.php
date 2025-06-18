<?php

use App\Http\Controllers\Admin\AdminSetupController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\EmergencyAccessController;
use App\Http\Controllers\Admin\MonitoringController;
// Temporarily commented out missing controllers
// use App\Http\Controllers\Admin\RoleController;
// use App\Http\Controllers\Admin\PermissionController;
// use App\Http\Controllers\Admin\UserRoleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin RBAC Routes
|--------------------------------------------------------------------------
|
| These routes handle the RBAC administration interface including
| audit logs, monitoring, emergency access, and role management.
|
*/

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {

    // Setup Management (System Administrators Only)
    Route::middleware('permission:system.manage')->group(function () {
        Route::get('/setup-status', [AdminSetupController::class, 'status'])->name('setup.status');
        Route::get('/setup-info', [AdminSetupController::class, 'getSetupInfo'])->name('setup.info');
        Route::post('/reset-setup', [AdminSetupController::class, 'resetSetup'])->name('setup.reset');
    });

    // Audit Log Management (Week 21 Focus)
    Route::middleware('permission:audit.view')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/audit/{audit}', [AuditController::class, 'show'])->name('audit.show');
        Route::post('/audit/export', [AuditController::class, 'export'])
            ->middleware('permission:audit.export')
            ->name('audit.export');
    });

    // Real-time Monitoring (Week 21 Focus)
    Route::middleware('permission:monitoring.view')->group(function () {
        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
        Route::get('/monitoring/metrics', [MonitoringController::class, 'metrics'])->name('monitoring.metrics');
        Route::post('/monitoring/export', [MonitoringController::class, 'export'])
            ->middleware('permission:monitoring.export')
            ->name('monitoring.export');
    });

    // Emergency Access Management (Week 21 Focus)
    Route::middleware('permission:emergency.view')->group(function () {
        Route::get('/emergency', [EmergencyAccessController::class, 'index'])->name('emergency.index');
        Route::post('/emergency/grant', [EmergencyAccessController::class, 'grant'])
            ->middleware('permission:emergency.grant')
            ->name('emergency.grant');
        Route::patch('/emergency/{emergency}/revoke', [EmergencyAccessController::class, 'revoke'])
            ->middleware('permission:emergency.revoke')
            ->name('emergency.revoke');
        Route::get('/emergency/{emergency}', [EmergencyAccessController::class, 'show'])
            ->name('emergency.show');
    });

    // TODO: Implement these controllers
    /*
    // Role Management
    Route::middleware('permission:roles.view')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('permission:roles.create')
            ->name('roles.store');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        Route::patch('/roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles.edit')
            ->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:roles.delete')
            ->name('roles.destroy');
    });

    // Permission Management
    Route::middleware('permission:permissions.view')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions', [PermissionController::class, 'store'])
            ->middleware('permission:permissions.create')
            ->name('permissions.store');
        Route::patch('/permissions/{permission}', [PermissionController::class, 'update'])
            ->middleware('permission:permissions.edit')
            ->name('permissions.update');
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])
            ->middleware('permission:permissions.delete')
            ->name('permissions.destroy');
    });

    // User Role Management
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [UserRoleController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/roles', [UserRoleController::class, 'show'])->name('users.roles.show');
        Route::post('/users/{user}/roles', [UserRoleController::class, 'assign'])
            ->middleware('permission:roles.assign')
            ->name('users.roles.assign');
        Route::delete('/users/{user}/roles/{role}', [UserRoleController::class, 'revoke'])
            ->middleware('permission:roles.revoke')
            ->name('users.roles.revoke');
        Route::post('/users/{user}/roles/temporary', [UserRoleController::class, 'assignTemporary'])
            ->middleware('permission:roles.assign_temporal')
            ->name('users.roles.temporary');
    });
    */
});

// Setup Management Routes (System Administrators Only)
Route::middleware(['auth', 'verified', 'permission:system.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/setup-status', [\App\Http\Controllers\Admin\AdminSetupController::class, 'status'])->name('setup.status');
        Route::get('/setup-info', [\App\Http\Controllers\Admin\AdminSetupController::class, 'getSetupInfo'])->name('setup.info');
        Route::post('/reset-setup', [\App\Http\Controllers\Admin\AdminSetupController::class, 'resetSetup'])->name('setup.reset');
    });

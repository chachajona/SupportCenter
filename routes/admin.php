<?php

use App\Http\Controllers\Admin\AdminSetupController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\EmergencyAccessController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\TemporalAccessController;
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
        Route::post('/emergency', [EmergencyAccessController::class, 'store'])
            ->middleware('permission:emergency.grant')
            ->name('emergency.store');
        Route::get('/emergency/{emergency}', [EmergencyAccessController::class, 'show'])
            ->name('emergency.show');
        Route::patch('/emergency/{emergency}/revoke', [EmergencyAccessController::class, 'revoke'])
            ->middleware('permission:emergency.revoke')
            ->name('emergency.revoke');
        Route::patch('/emergency/{emergency}/use', [EmergencyAccessController::class, 'markUsed'])
            ->middleware('permission:emergency.manage')
            ->name('emergency.use');
        Route::post('/emergency/cleanup', [EmergencyAccessController::class, 'cleanup'])
            ->middleware('permission:emergency.manage')
            ->name('emergency.cleanup');
    });

    // Analytics Dashboard (Week 21 Focus)
    Route::middleware('permission:analytics.view')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/analytics/refresh', [AnalyticsController::class, 'refresh'])->name('analytics.refresh');
        Route::get('/analytics/metrics', [AnalyticsController::class, 'metrics'])->name('analytics.metrics');
        Route::get('/analytics/export', [AnalyticsController::class, 'export'])
            ->middleware('permission:analytics.export')
            ->name('analytics.export');
    });

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
        Route::get('/roles/matrix/view', [RoleController::class, 'matrix'])
            ->middleware('permission:roles.view_matrix')
            ->name('roles.matrix');
        Route::patch('/roles/matrix/update', [RoleController::class, 'updateMatrix'])
            ->middleware('permission:roles.edit_matrix')
            ->name('roles.matrix.update');
    });

    // User Role Management
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [UserRoleController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserRoleController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/permissions', [UserRoleController::class, 'getUserPermissions'])->name('users.permissions');

        Route::post('/users/{user}/roles', [UserRoleController::class, 'assign'])
            ->middleware('permission:roles.assign')
            ->name('users.roles.assign');
        Route::delete('/users/{user}/roles/{role}', [UserRoleController::class, 'revoke'])
            ->middleware('permission:roles.revoke')
            ->name('users.roles.revoke');
        Route::post('/users/{user}/temporal-access', [UserRoleController::class, 'assignTemporary'])
            ->middleware('permission:roles.assign_temporal')
            ->name('users.temporal.assign');
        Route::delete('/users/{user}/temporal-access/{role}', [UserRoleController::class, 'revokeTemporary'])
            ->middleware('permission:roles.revoke_temporal')
            ->name('users.temporal.revoke');
        Route::post('/users/{user}/temporal-access/approve', [UserRoleController::class, 'approveTemporal'])
            ->middleware('permission:roles.approve_temporal')
            ->name('users.temporal.approve');
        Route::delete('/users/{user}/temporal-access/{role}/deny', [UserRoleController::class, 'denyTemporal'])
            ->middleware('permission:roles.deny_temporal')
            ->name('users.temporal.deny');
        Route::post('/users/{user}/temporal-access/request', [TemporalAccessController::class, 'requestAccess'])
            ->middleware('permission:roles.request_temporal')
            ->name('users.temporal.request');

        // Bulk operations
        Route::post('/users/bulk/assign', [UserRoleController::class, 'bulkAssign'])
            ->middleware('permission:roles.bulk_assign')
            ->name('users.bulk.assign');
        Route::post('/users/bulk/revoke', [UserRoleController::class, 'bulkRevoke'])
            ->middleware('permission:roles.bulk_revoke')
            ->name('users.bulk.revoke');
    });

    // Temporal Access Management
    Route::get('/temporal', [TemporalAccessController::class, 'index'])
        ->middleware(['permission:roles.approve_temporal,roles.deny_temporal'])
        ->name('temporal.index');
    Route::post('/temporal/{user}/approve', [TemporalAccessController::class, 'approveRequest'])
        ->middleware('permission:roles.approve_temporal')
        ->name('temporal.approve');
    Route::post('/temporal/{user}/{role}/deny', [TemporalAccessController::class, 'denyRequest'])
        ->middleware('permission:roles.deny_temporal')
        ->name('temporal.deny');
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

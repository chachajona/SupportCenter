<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Http\Controllers\Settings\TwoFactorQrCodeController;
use App\Http\Controllers\SetupController;

// Setup routes - only accessible before setup completion
Route::prefix('setup')->name('setup.')->middleware(['web', 'prevent.setup.access'])->group(function () {
    Route::get('/', [SetupController::class, 'index'])->name('index');

    // Specific routes for each step
    Route::get('/prerequisites', [SetupController::class, 'showPrerequisites'])->name('prerequisites');
    Route::get('/database', [SetupController::class, 'showDatabase'])->name('database');
    Route::post('/database', [SetupController::class, 'saveDatabase'])->name('database.save');
    Route::post('/migrate', [SetupController::class, 'runMigrations'])->name('migrate');
    Route::post('/seed', [SetupController::class, 'seedRolesAndPermissions'])->name('seed');
    Route::get('/roles-seeded', [SetupController::class, 'showRolesSeeded'])->name('roles_seeded');
    Route::get('/app-settings', [SetupController::class, 'showAppSettings'])->name('app_settings');
    Route::post('/admin', [SetupController::class, 'createAdmin'])->name('admin.create');

    Route::post('/complete', [SetupController::class, 'completeSetup'])->name('complete');
    Route::get('/complete', [SetupController::class, 'showComplete']);
    Route::post('/reset', [SetupController::class, 'resetSetup'])->name('reset');
});

// All other routes require setup completion
Route::middleware(['setup.completed'])->group(function () {
    Route::get('/', function () {
        return Inertia::render('welcome');
    })->name('home');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('dashboard', function () {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();
            if ($user) {
                $user->load(['roles.permissions', 'department']);
            }

            return Inertia::render('dashboard', [
                'auth' => [
                    'user' => $user
                ]
            ]);
        })->name('dashboard');

        // Two-Factor QR Code route - using standard auth middleware for consistency
        Route::get('/user/two-factor-qr-code', [TwoFactorQrCodeController::class, 'show'])
            ->name('two-factor.qr-code');
    });

    require __DIR__ . '/settings.php';
    require __DIR__ . '/auth.php';
    require __DIR__ . '/admin.php';
});

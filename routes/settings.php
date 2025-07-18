<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\WebAuthnSettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');

    Route::get('settings/security', function () {
        return Inertia::render('settings/two-factor-authentication');
    })->name('security');

    // WebAuthn Settings Routes
    Route::get('settings/webauthn', [WebAuthnSettingsController::class, 'show'])->name('webauthn.settings');

    Route::post('/user/webauthn/enable', [WebAuthnSettingsController::class, 'enable'])
        ->middleware(['password.confirm'])
        ->name('webauthn.enable');

    Route::delete('/user/webauthn/disable', [WebAuthnSettingsController::class, 'disable'])
        ->middleware(['password.confirm'])
        ->name('webauthn.disable');

    Route::get('/user/webauthn/credentials', [WebAuthnSettingsController::class, 'credentials'])
        ->name('webauthn.credentials');
});

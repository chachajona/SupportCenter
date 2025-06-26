<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\CustomTwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\TwoFactorSecretKeyController;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\ConfirmedTwoFactorAuthenticationController;
use App\Http\Controllers\Auth\WebAuthnLoginController;
use App\Http\Controllers\Auth\WebAuthnRegisterController;
use App\Http\Controllers\Auth\WebAuthnManageController;
use App\Http\Controllers\Auth\TwoFactorChoiceController;
use App\Http\Controllers\Auth\EmergencyAccessController;

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

// Email Verification Routes
Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->name('password.confirm.store');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::post('/break-glass', [AuthenticatedSessionController::class, 'breakGlass'])
        ->name('break-glass');
});

// Two-Factor Authentication Routes
Route::get('/two-factor-challenge', [CustomTwoFactorAuthenticatedSessionController::class, 'create'])
    ->middleware(['web', 'two-factor.challenge'])
    ->name('two-factor.login');

Route::post('/two-factor-challenge', [CustomTwoFactorAuthenticatedSessionController::class, 'store'])
    ->middleware(['web', 'two-factor.challenge', 'throttle:two-factor'])
    ->name('two-factor.login.store');

// Two-Factor Method Choice
Route::get('/two-factor-choice', [TwoFactorChoiceController::class, 'show'])
    ->middleware(['web'])
    ->name('two-factor.choice');

Route::post('/two-factor-choice', [TwoFactorChoiceController::class, 'select'])
    ->middleware(['web'])
    ->name('two-factor.choice.select');

// Two-Factor Authentication Management Routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Enable 2FA
    Route::post('/user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'store'])
        ->middleware(['password.confirm'])
        ->name('two-factor.enable');

    // Disable 2FA
    Route::delete('/user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'destroy'])
        ->middleware(['password.confirm'])
        ->name('two-factor.disable');

    // Confirm 2FA setup - this doesn't need password confirmation since enabling already required it
    Route::post('/user/confirmed-two-factor-authentication', [ConfirmedTwoFactorAuthenticationController::class, 'store'])
        ->name('two-factor.confirm');

    // Get secret key for manual entry
    Route::get('/user/two-factor-secret-key', [TwoFactorSecretKeyController::class, 'show'])
        ->name('two-factor.secret-key');

    // Get/Generate recovery codes - these also don't need password confirmation if 2FA is already enabled
    Route::get('/user/two-factor-recovery-codes', [RecoveryCodeController::class, 'index'])
        ->name('two-factor.recovery-codes');

    Route::post('/user/two-factor-recovery-codes', [RecoveryCodeController::class, 'store'])
        ->middleware(['password.confirm'])
        ->name('two-factor.recovery-codes.store');
});

// WebAuthn Authentication Routes (guest users)
Route::middleware(['guest', 'webauthn.security'])->group(function () {
    Route::post('/webauthn/login/options', [WebAuthnLoginController::class, 'options'])
        ->name('webauthn.login.options');

    Route::post('/webauthn/login', [WebAuthnLoginController::class, 'authenticate'])
        ->middleware('throttle:two-factor')
        ->name('webauthn.login');
});

// WebAuthn Registration Routes (authenticated users)
Route::middleware(['auth', 'verified', 'webauthn.security'])->group(function () {
    // Remove unused GET route - registration is now handled in settings
    // Route::get('/user/webauthn/register', [WebAuthnRegisterController::class, 'create'])
    //     ->name('webauthn.register.create');

    Route::post('/user/webauthn/register/options', [WebAuthnRegisterController::class, 'options'])
        ->name('webauthn.register.options');

    Route::post('/user/webauthn/register', [WebAuthnRegisterController::class, 'store'])
        ->middleware(['password.confirm'])
        ->name('webauthn.register.store');

    Route::delete('/user/webauthn/{credential}', [WebAuthnManageController::class, 'destroy'])
        ->middleware(['password.confirm'])
        ->name('webauthn.destroy');

    Route::put('/user/webauthn/{credential}', [WebAuthnManageController::class, 'update'])
        ->middleware(['password.confirm'])
        ->name('webauthn.update');
});

// Emergency Access Routes
Route::middleware('guest')->group(function () {
    Route::get('/emergency-access', [EmergencyAccessController::class, 'show'])
        ->name('emergency.access');

    Route::post('/emergency-access', [EmergencyAccessController::class, 'initiate'])
        ->middleware('throttle:3,60')
        ->name('emergency.access.initiate');

    Route::get('/emergency-access/{token}', [EmergencyAccessController::class, 'process'])
        ->middleware('throttle:5,60')
        ->name('emergency.access.process');
});

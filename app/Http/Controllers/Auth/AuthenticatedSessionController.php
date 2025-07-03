<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\EmergencyAccess;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): JsonResponse|RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $usernameField = config('fortify.username');
        $user = User::where($usernameField, $request->input($usernameField))->first();

        $hashedPassword = $user?->password ?? Hash::make(str()->random(32));

        if (! $user || ! Hash::check($request->input('password'), $hashedPassword)) {
            RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages([
                $usernameField => [trans('auth.failed')],
            ]);
        }

        if (
            method_exists($user, 'hasEnabledTwoFactorAuthentication') &&
            $user->hasEnabledTwoFactorAuthentication() &&
            $request->wantsJson()
        ) {
            $request->session()->regenerate();
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
                'last_activity_time' => time(),
            ]);

            RateLimiter::clear($request->throttleKey());

            // Check for multiple MFA methods
            $availableMethods = [];
            if ($user->two_factor_enabled) {
                $availableMethods[] = 'totp';
            }
            if ($user->hasWebAuthnCredentials()) {
                $availableMethods[] = 'webauthn';
            }

            // If multiple methods available, redirect to choice page
            if (count($availableMethods) > 1) {
                return response()->json(['two_factor_choice' => true]);
            }

            return response()->json(['two_factor' => true]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // Set last activity time to prevent immediate session timeout
        $request->session()->put('last_activity_time', time());

        RateLimiter::clear($request->throttleKey());

        if ($request->wantsJson()) {
            return response()->json(['redirectTo' => route('dashboard', absolute: false)]);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Login using break-glass emergency token
     */
    public function breakGlass(Request $request)
    {
        $request->validate([
            'token' => 'required|uuid',
        ]);

        $emergencyAccess = EmergencyAccess::where('token', $request->token)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->first();

        if (! $emergencyAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired break-glass token',
            ], 422);
        }

        // Mark token as used
        $emergencyAccess->markTokenUsed();

        // Log the user in
        Auth::login($emergencyAccess->user);

        // Log security event
        SecurityLog::create([
            'user_id' => $emergencyAccess->user_id,
            'event_type' => \App\Enums\SecurityEventType::AUTH_SUCCESS,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => [
                'type' => 'break_glass_login',
                'emergency_access_id' => $emergencyAccess->id,
                'permissions' => $emergencyAccess->permissions,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Break-glass login successful',
            'redirect' => route('dashboard'),
        ]);
    }
}

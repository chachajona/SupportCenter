<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

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

        if (!$user || !Hash::check($request->input('password'), $hashedPassword)) {
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
            ]);

            RateLimiter::clear($request->throttleKey());

            return response()->json(['two_factor' => true]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

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
}

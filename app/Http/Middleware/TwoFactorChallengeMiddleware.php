<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorChallengeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * This middleware ensures that:
     * 1. Users can only access 2FA challenge routes when they're in a 2FA challenge state
     * 2. Fully authenticated users are redirected away from 2FA challenge routes
     * 3. Guests without a pending 2FA challenge are redirected to login
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is fully authenticated (completed 2FA)
        if (Auth::check()) {
            // If user is fully authenticated, they shouldn't be on 2FA challenge pages
            return redirect()->intended('/dashboard');
        }

        // Check if there's a pending 2FA challenge in the session
        // Fortify stores the user ID in session during 2FA challenge
        $hasPending2FA = $request->session()->has('login.id') ||
            $request->session()->has('two_factor_login_user_id');

        if (! $hasPending2FA) {
            // No pending 2FA challenge, redirect to login
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        // User has a pending 2FA challenge, allow access to 2FA routes
        return $next($request);
    }
}

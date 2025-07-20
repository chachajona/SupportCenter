<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorConfirmedMiddleware
{
    /**
     * Handle an incoming request.
     *
     * This middleware ensures that users have completed two-factor authentication
     * before accessing sensitive endpoints.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // If user is not authenticated, let auth middleware handle it
        if (!$user) {
            return $next($request);
        }

        // Helper closure to build either a JSON or redirect response based on request expectations
        $respond = static function (Request $req, string $message, string $routeName, int $status = 428): Response {
            if ($req->expectsJson() || $req->wantsJson()) {
                return response()->json([
                    'error' => $message,
                    'redirect' => route($routeName),
                ], $status);
            }

            return redirect()->guest(route($routeName))->withErrors(['two_factor' => $message]);
        };

        // Check if user has 2FA enabled (either TOTP or WebAuthn)
        if (!$user->two_factor_enabled && !$user->hasWebAuthnCredentials()) {
            return $respond($request, 'Two-factor authentication is required for this action.', 'two-factor.enable');
        }

        // Retrieve and validate confirmation timestamp
        $confirmedAtRaw = $request->session()->get('two_factor_confirmed_at');
        $timestamp = filter_var($confirmedAtRaw, FILTER_VALIDATE_INT) ? (int) $confirmedAtRaw : null;

        // Configurable TTL (defaults to 3 hours / 10 800 s)
        $ttlSeconds = (int) config('security.two_factor_confirmation_ttl', 10_800);

        // If not confirmed or expired, force re-authentication
        if (!$timestamp) {
            return $respond($request, 'Please complete two-factor authentication.', 'two-factor.login');
        }

        if ($timestamp < now()->subSeconds($ttlSeconds)->timestamp) {
            $request->session()->forget('two_factor_confirmed_at');

            return $respond($request, 'Two-factor authentication has expired. Please re-authenticate.', 'two-factor.login');
        }

        return $next($request);
    }
}

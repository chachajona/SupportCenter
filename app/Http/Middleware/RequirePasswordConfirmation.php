<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

final class RequirePasswordConfirmation
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->shouldConfirmPassword($request)) {
            return $next($request);
        }

        // For API requests or routes that return JSON, return 423 status code
        if ($request->expectsJson() || $this->shouldReturnJson($request)) {
            return response()->json([
                'message' => 'Password confirmation required.'
            ], 423);
        }

        // For web requests, redirect to password confirmation
        return redirect()->guest(route('password.confirm'));
    }

    /**
     * Determine if the password confirmation is required.
     */
    protected function shouldConfirmPassword(Request $request): bool
    {
        $confirmedAt = time() - $request->session()->get('auth.password_confirmed_at', 0);

        return $confirmedAt > Config::get('auth.password_timeout', 10800);
    }

    /**
     * Determine if the request should return JSON response.
     */
    protected function shouldReturnJson(Request $request): bool
    {
        // Routes that always return JSON responses
        $jsonRoutes = [
            '/user/webauthn/enable',
            '/user/webauthn/disable',
            '/user/webauthn/credentials',
            '/user/two-factor-authentication',
            '/user/two-factor-recovery-codes',
        ];

        return in_array($request->getPathInfo(), $jsonRoutes);
    }
}

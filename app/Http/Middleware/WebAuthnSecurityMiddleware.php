<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class WebAuthnSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Rate limit WebAuthn attempts per IP
        $key = 'webauthn:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many WebAuthn attempts. Please try again later.'
            ], 429);
        }

        RateLimiter::increment($key, 60); // 1 minute decay

        // Ensure HTTPS in production
        if (app()->environment('production') && !$request->secure()) {
            return response()->json([
                'success' => false,
                'message' => 'WebAuthn requires a secure connection.'
            ], 400);
        }

        return $next($request);
    }
}

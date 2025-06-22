<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AdvancedRateLimiter
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        $key = $this->resolveKey($request);

        if (RateLimiter::tooManyAttempts($key, 50)) {
            $retryAfter = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Too many operations. Please slow down.',
            ], Response::HTTP_TOO_MANY_REQUESTS)->header('Retry-After', (string) $retryAfter);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }

    private function resolveKey(Request $request): string
    {
        $userId = Auth::id();
        $operation = $request->route()?->getName() ?? $request->path();
        return sprintf('op_rate:%s:%s', $userId ?: $request->ip(), sha1($operation));
    }
}

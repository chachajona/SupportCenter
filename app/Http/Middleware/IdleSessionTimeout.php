<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

final class IdleSessionTimeout
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $idleTimeout = Config::get('session.idle_timeout', 1800); // 30 minutes default
        $lastActivity = $request->session()->get('last_activity_time', 0);
        $currentTime = time();

        // If last_activity_time doesn't exist (new session), initialize it
        if ($lastActivity === 0) {
            $request->session()->put('last_activity_time', $currentTime);

            return $next($request);
        }

        if (($currentTime - $lastActivity) > $idleTimeout) {
            $this->performSecureLogout($request);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Session expired due to inactivity.',
                    'redirect' => '/login',
                ], 401);
            }

            return redirect('/login')->with('status', 'Session expired due to inactivity.');
        }

        // Update last activity time
        $request->session()->put('last_activity_time', $currentTime);

        return $next($request);
    }

    /**
     * Perform a secure logout that works with different guard types.
     */
    private function performSecureLogout(Request $request): void
    {
        $guard = Auth::guard();

        // Check if the guard has a logout method (session-based guards)
        if (method_exists($guard, 'logout')) {
            $guard->logout();
        } else {
            // For guards without logout method (like RequestGuard), clear the user manually
            Auth::forgetUser();
        }

        // Always invalidate and regenerate session for security
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}

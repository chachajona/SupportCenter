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
        if (!Auth::check()) {
            return $next($request);
        }

        $idleTimeout = Config::get('session.idle_timeout', 1800); // 30 minutes default
        $lastActivity = $request->session()->get('last_activity_time', 0);
        $currentTime = time();

        if (($currentTime - $lastActivity) > $idleTimeout) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Session expired due to inactivity.',
                    'redirect' => '/login'
                ], 401);
            }

            return redirect('/login')->with('status', 'Session expired due to inactivity.');
        }

        // Update last activity time
        $request->session()->put('last_activity_time', $currentTime);

        return $next($request);
    }
}

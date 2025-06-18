<?php

namespace App\Http\Middleware;

use App\Models\SetupStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetupMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass during automated tests to allow unrelated feature/unit tests to
        // access protected routes without requiring the installation wizard.
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        // Skip setup check for setup routes and API routes
        if ($request->is('setup*') || $request->is('api/*')) {
            return $next($request);
        }

        // Check if setup is completed via lock file (faster than DB query)
        $setupLockFile = storage_path('app/setup.lock');
        if (file_exists($setupLockFile)) {
            return $next($request);
        }

        // Fallback to database check
        if (SetupStatus::isSetupCompleted()) {
            // Create lock file for future requests
            file_put_contents($setupLockFile, json_encode([
                'completed_at' => now()->toISOString(),
                'completed_by' => 'system_check',
                'version' => config('app.version', '1.0.0'),
            ]));

            return $next($request);
        }

        // Setup is not completed, redirect to setup
        return redirect()->route('setup.index');
    }
}

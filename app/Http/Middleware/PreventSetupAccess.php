<?php

namespace App\Http\Middleware;

use App\Models\SetupStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PreventSetupAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if setup is already completed via lock file (fastest check)
        $setupLockFile = storage_path('app/setup.lock');
        if (file_exists($setupLockFile)) {
            return redirect()->route('login')->with('info', 'Setup has already been completed.');
        }

        // Only check database if a connection is established
        if ($this->isDatabaseConnected() && SetupStatus::isSetupCompleted()) {
            return redirect()->route('login')->with('info', 'Setup has already been completed.');
        }

        return $next($request);
    }

    private function isDatabaseConnected(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

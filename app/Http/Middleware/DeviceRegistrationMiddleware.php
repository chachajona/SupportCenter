<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Device;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class DeviceRegistrationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Skip device checks during automated tests
        if (app()->environment('testing')) {
            return $next($request);
        }

        $user = Auth::user();
        /** @var User|null $user */
        if (! $user) {
            return $next($request);
        }

        // If the user_devices table doesn't exist yet (e.g., before migrations), skip the check to avoid SQL errors
        if (! Schema::hasTable('user_devices')) {
            return $next($request);
        }

        $userAgent = (string) $request->header('User-Agent', '');
        $ip = $request->ip();
        $deviceHash = hash('sha256', $userAgent.'|'.($request->server('HTTP_SEC_CH_UA') ?? ''));

        $device = Device::firstOrCreate([
            'user_id' => $user->id,
            'device_hash' => $deviceHash,
        ], [
            'user_agent' => Str::limit($userAgent, 1024),
            'ip_address' => $ip,
            'last_used_at' => now(),
        ]);

        // Update last used timestamp
        $device->update(['last_used_at' => now()]);

        // Auto-verify device in non-production environments or for the first system administrator to avoid developer lockout
        if (is_null($device->verified_at) && (app()->environment('local', 'testing') || $user->hasRole('system_administrator'))) {
            $device->update(['verified_at' => now()]);
        }

        // If the device is not yet verified, block the request and prompt verification
        if (is_null($device->verified_at)) {
            return response()->json([
                'message' => 'Unrecognized device detected. Please verify this device via the link sent to your email.',
            ], 403);
        }

        return $next($request);
    }
}

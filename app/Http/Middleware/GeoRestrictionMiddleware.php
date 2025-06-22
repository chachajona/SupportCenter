<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class GeoRestrictionMiddleware
{
    /** @var array<string> */
    private array $allowedCountries;

    public function __construct()
    {
        $this->allowedCountries = config('security.allowed_countries', ['US', 'CA', 'GB']);
    }

    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        $ip = $request->ip();

        // Skip for local environment or private IPs
        if ($request->isFromTrustedProxy() || $this->isPrivateIp($ip)) {
            return $next($request);
        }

        $country = Cache::remember("geoip:{$ip}", 86400, fn() => $this->lookupCountry($ip));

        if ($country !== null && !in_array($country, $this->allowedCountries, true)) {
            return response()->json([
                'message' => 'Access from your region is not allowed.',
            ], 403);
        }

        return $next($request);
    }

    private function lookupCountry(string $ip): ?string
    {
        try {
            $response = Http::get("https://ipapi.co/{$ip}/country/");
            if ($response->successful()) {
                return trim($response->body());
            }
        } catch (\Throwable) {
            // Ignore lookup failures
        }

        return null;
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}

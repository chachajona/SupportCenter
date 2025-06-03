<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SecurityEventType;
use App\Models\IpAllowlist;
use App\Models\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class IpAllowlistMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $request->ip();
        $userAgent = $request->userAgent();
        $user = Auth::user();

        // Check if IP is in allowlist (only for authenticated users)
        if ($user && !$this->isIpAllowed($clientIp, $user->id)) {
            // Log blocked access attempt
            SecurityLog::create([
                'user_id' => $user->id,
                'event_type' => SecurityEventType::IP_BLOCKED,
                'ip_address' => $clientIp,
                'user_agent' => $userAgent,
                'details' => json_encode([
                    'blocked_ip' => $clientIp,
                    'reason' => 'IP not in allowlist'
                ]),
                'created_at' => now(),
            ]);

            $this->performSecureLogout($request);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Access denied: IP address not authorized.',
                ], 403);
            }

            return redirect('/login')->withErrors([
                'ip' => 'Access denied: Your IP address is not authorized for this account.'
            ]);
        }

        // Log successful access for audit trail (rate-limited to prevent database bloat)
        if ($user) {
            $this->logAccessIfNeeded($user->id, $clientIp, $userAgent, $request);
        }

        return $next($request);
    }

    /**
     * Log access attempt with rate limiting to prevent database bloat.
     * Only logs if no log exists for this user/IP combination in the last 5 minutes.
     */
    private function logAccessIfNeeded(int $userId, string $clientIp, ?string $userAgent, Request $request): void
    {
        $cacheKey = "access_log:{$userId}:{$clientIp}";

        // Use Cache::add() for atomic operation to prevent race conditions
        if (Cache::add($cacheKey, true, now()->addMinutes(5))) {
            SecurityLog::create([
                'user_id' => $userId,
                'event_type' => SecurityEventType::ACCESS_GRANTED,
                'ip_address' => $clientIp,
                'user_agent' => $userAgent,
                'details' => json_encode([
                    'route' => $request->route()?->getName(),
                    'method' => $request->method()
                ]),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Check if IP address is allowed for the user.
     */
    private function isIpAllowed(string $ip, int $userId): bool
    {
        // If no allowlist entries exist for user, allow all IPs
        $allowlistEntries = IpAllowlist::where('user_id', $userId)->where('is_active', true)->get();

        if ($allowlistEntries->isEmpty()) {
            return true; // No restrictions when no allowlist is configured
        }

        // Check exact IP matches
        if ($allowlistEntries->where('ip_address', $ip)->isNotEmpty()) {
            return true;
        }

        // Check CIDR ranges
        foreach ($allowlistEntries as $entry) {
            if ($entry->cidr_range && $this->ipInCidr($ip, $entry->cidr_range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is within CIDR range.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        // Validate CIDR format
        if (!str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);

        // Validate mask is numeric
        if (!is_numeric($mask)) {
            return false;
        }

        $mask = (int) $mask;

        // Handle IPv4
        if (
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ) {

            // Validate IPv4 mask range
            if ($mask < 0 || $mask > 32) {
                return false;
            }

            return $this->ipv4InCidr($ip, $subnet, $mask);
        }

        // Handle IPv6
        if (
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
        ) {

            // Validate IPv6 mask range
            if ($mask < 0 || $mask > 128) {
                return false;
            }

            return $this->ipv6InCidr($ip, $subnet, $mask);
        }

        return false;
    }

    /**
     * Check if IPv4 address is within CIDR range using inet_pton approach.
     */
    private function ipv4InCidr(string $ip, string $subnet, int $mask): bool
    {
        $ipBinary = inet_pton($ip);
        $subnetBinary = inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false) {
            return false;
        }

        // Convert IPv4 addresses to 32-bit integers for comparison
        // This avoids bit-shifting overflow issues on 32-bit systems
        $ipInt = unpack('N', $ipBinary)[1];
        $subnetInt = unpack('N', $subnetBinary)[1];

        // Create network mask safely
        if ($mask === 0) {
            $maskInt = 0;
        } else {
            $maskInt = 0xFFFFFFFF << (32 - $mask);
        }

        return ($ipInt & $maskInt) === ($subnetInt & $maskInt);
    }

    /**
     * Check if IPv6 address is within CIDR range.
     */
    private function ipv6InCidr(string $ip, string $subnet, int $mask): bool
    {
        $ipBinary = inet_pton($ip);
        $subnetBinary = inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false) {
            return false;
        }

        // Calculate the number of bytes and bits to compare
        $bytesToCompare = intval($mask / 8);
        $bitsToCompare = $mask % 8;

        // Compare full bytes
        for ($i = 0; $i < $bytesToCompare; $i++) {
            if ($ipBinary[$i] !== $subnetBinary[$i]) {
                return false;
            }
        }

        // Compare remaining bits if any
        if ($bitsToCompare > 0 && $bytesToCompare < strlen($ipBinary)) {
            $ipByte = ord($ipBinary[$bytesToCompare]);
            $subnetByte = ord($subnetBinary[$bytesToCompare]);
            $mask = 0xFF << (8 - $bitsToCompare);

            if (($ipByte & $mask) !== ($subnetByte & $mask)) {
                return false;
            }
        }

        return true;
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

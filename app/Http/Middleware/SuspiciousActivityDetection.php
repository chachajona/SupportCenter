<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SecurityEventType;
use App\Models\SecurityLog;
use App\Notifications\SuspiciousActivityAlert;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;

final class SuspiciousActivityDetection
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        $clientIp = $request->ip();
        $userAgent = $request->userAgent();
        $suspiciousScore = 0;
        $alerts = [];

        // Check for multiple failed login attempts
        $failedLogins = Cache::get("failed_logins_{$clientIp}", 0);
        if ($failedLogins >= 3) {
            $suspiciousScore += 30;
            $alerts[] = "Multiple failed login attempts from IP: {$clientIp}";
        }

        // Check for unusual geographical login (simplified - in production use GeoIP)
        $lastKnownIp = Cache::get("last_ip_{$user->id}");
        if ($lastKnownIp && $lastKnownIp !== $clientIp) {
            $suspiciousScore += 20;
            $alerts[] = "Login from new IP address: {$clientIp} (previous: {$lastKnownIp})";
        }

        // Check for unusual user agent
        $lastKnownUserAgent = Cache::get("last_user_agent_{$user->id}");
        if ($lastKnownUserAgent && $lastKnownUserAgent !== $userAgent) {
            $suspiciousScore += 15;
            $alerts[] = "Login from new device/browser";
        }

        // Check for rapid session creation (potential session hijacking)
        $sessionKey = "session_count_{$user->id}";
        $sessionCount = Cache::get($sessionKey, 0);
        Cache::put($sessionKey, $sessionCount + 1, 300); // 5 minutes

        if ($sessionCount > 5) {
            $suspiciousScore += 40;
            $alerts[] = "Unusual number of concurrent sessions";
        }

        // Check for access outside normal hours (if configured)
        $currentHour = now()->hour;
        if ($currentHour < 6 || $currentHour > 22) { // Outside 6 AM - 10 PM
            $suspiciousScore += 10;
            $alerts[] = "Access outside normal business hours";
        }

        // Log suspicious activity
        if ($suspiciousScore > 0) {
            SecurityLog::create([
                'user_id' => $user->id,
                'event_type' => SecurityEventType::SUSPICIOUS_ACTIVITY,
                'ip_address' => $clientIp,
                'user_agent' => $userAgent,
                'details' => json_encode([
                    'suspicious_score' => $suspiciousScore,
                    'alerts' => $alerts,
                    'request_path' => $request->path(),
                ]),
                'created_at' => now(),
            ]);
        }

        // Send alert if score is high enough
        if ($suspiciousScore >= 50) {
            $this->sendSuspiciousActivityAlert($user, $clientIp, $alerts, $suspiciousScore);

            // Optionally force logout for high-risk activities
            if ($suspiciousScore >= 80) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Session terminated due to suspicious activity.',
                        'redirect' => '/login'
                    ], 401);
                }

                return redirect('/login')->withErrors([
                    'security' => 'Session terminated due to suspicious activity. Please contact support if this was legitimate access.'
                ]);
            }
        }

        // Update tracking data
        Cache::put("last_ip_{$user->id}", $clientIp, 86400); // 24 hours
        Cache::put("last_user_agent_{$user->id}", $userAgent, 86400); // 24 hours

        return $next($request);
    }

    /**
     * Send suspicious activity alert email.
     */
    private function sendSuspiciousActivityAlert($user, string $ip, array $alerts, int $score): void
    {
        $alertKey = "alert_sent_{$user->id}_{$ip}";

        // Prevent spam - only send one alert per IP per hour
        if (Cache::has($alertKey)) {
            return;
        }

        try {
            $user->notify(new SuspiciousActivityAlert([
                'ip_address' => $ip,
                'alerts' => $alerts,
                'score' => $score,
                'timestamp' => now(),
            ]));

            Cache::put($alertKey, true, 3600); // 1 hour
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send suspicious activity alert', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

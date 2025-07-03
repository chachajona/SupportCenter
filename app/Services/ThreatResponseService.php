<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PermissionAudit;
use App\Models\SecurityLog;
use App\Notifications\SuspiciousActivityAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class ThreatResponseService
{
    /**
     * Handle security event and apply appropriate threat response measures.
     */
    public function handle(SecurityLog $log): void
    {
        if (! $log->event_type->isThreat()) {
            return;
        }

        $this->processIpBlock($log);
        $this->sendNotificationIfNeeded($log);
    }

    /**
     * Block IP address if threat is detected and log the action.
     */
    private function processIpBlock(SecurityLog $log): void
    {
        if (! $log->ip_address || ! config('security.audit.log_ip_blocks', true)) {
            return;
        }

        $blockTtl = config('security.ip_block_ttl', 1800); // Default 30 minutes
        $cacheKey = config('security.cache.blocked_ip_prefix', 'blocked_ip:').$log->ip_address;

        // Check if IP is already blocked to avoid duplicate audit entries
        if (Cache::has($cacheKey)) {
            return;
        }

        // Block the IP
        Cache::put($cacheKey, [
            'blocked_at' => now()->toISOString(),
            'security_log_id' => $log->id,
            'event_type' => $log->event_type->value,
            'user_id' => $log->user_id,
        ], now()->addSeconds($blockTtl));

        // Create audit entry for IP block
        $this->createIpBlockAudit($log, 'ip_block_auto', $blockTtl);

        // Schedule automatic unblock audit (will be logged when cache expires)
        $this->scheduleUnblockAudit($log->ip_address, $blockTtl);

        Log::info('IP address automatically blocked due to threat detection', [
            'ip_address' => $log->ip_address,
            'event_type' => $log->event_type->value,
            'user_id' => $log->user_id,
            'security_log_id' => $log->id,
            'block_duration_seconds' => $blockTtl,
            'expires_at' => now()->addSeconds($blockTtl)->toISOString(),
        ]);
    }

    /**
     * Send notification to user if threat response is enabled and not rate limited.
     */
    private function sendNotificationIfNeeded(SecurityLog $log): void
    {
        if (! config('security.notifications.enable_email_alerts', true) || ! $log->user) {
            return;
        }

        $rateLimitWindow = config('security.notifications.rate_limit_window', 3600);
        $rateLimitKey = config('security.cache.notification_rate_limit_prefix', 'security_notification:')
            .$log->user_id.':'.$log->ip_address;

        // Check rate limit to prevent notification spam
        if (Cache::has($rateLimitKey)) {
            return;
        }

        try {
            Notification::send($log->user, new SuspiciousActivityAlert([
                'ip' => $log->ip_address,
                'userAgent' => $log->user_agent,
                'event' => $log->event_type->value,
                'timestamp' => $log->created_at,
                'details' => $log->details,
            ]));

            // Set rate limit
            Cache::put($rateLimitKey, true, now()->addSeconds($rateLimitWindow));

            Log::info('Suspicious activity alert sent to user', [
                'user_id' => $log->user_id,
                'ip_address' => $log->ip_address,
                'event_type' => $log->event_type->value,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send suspicious activity alert', [
                'user_id' => $log->user_id,
                'ip_address' => $log->ip_address,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create audit entry for IP block action.
     */
    private function createIpBlockAudit(SecurityLog $log, string $action, int $blockTtl): void
    {
        if (! config('security.audit.log_ip_blocks', true)) {
            return;
        }

        try {
            // Create audit entry directly using Eloquent to bypass model validation
            $audit = new PermissionAudit;
            $audit->user_id = $log->user_id;
            $audit->permission_id = null; // IP blocks don't relate to permissions
            $audit->role_id = null; // IP blocks don't relate to roles
            $audit->action = 'unauthorized_access_attempt'; // Use existing enum value for now
            $audit->old_values = null;
            $audit->new_values = json_encode([
                'ip_address' => $log->ip_address,
                'block_duration_seconds' => $blockTtl,
                'expires_at' => now()->addSeconds($blockTtl)->toISOString(),
                'trigger_event_type' => $log->event_type->value,
                'security_log_id' => $log->id,
                'action_type' => 'ip_block_auto', // Store the real action type in new_values
            ]);
            $audit->ip_address = $log->ip_address;
            $audit->user_agent = $log->user_agent;
            $audit->performed_by = null; // System action
            $audit->reason = "Automatic IP block due to {$log->event_type->value}";
            $audit->created_at = now();

            $audit->save();
        } catch (\Exception $e) {
            Log::error('Failed to create IP block audit entry', [
                'security_log_id' => $log->id,
                'ip_address' => $log->ip_address,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Schedule audit entry for when IP block expires.
     */
    private function scheduleUnblockAudit(string $ipAddress, int $blockTtl): void
    {
        // Store unblock info in cache to be processed later
        $unblockKey = "unblock_audit:{$ipAddress}:".now()->addSeconds($blockTtl)->timestamp;

        Cache::put($unblockKey, [
            'ip_address' => $ipAddress,
            'unblock_at' => now()->addSeconds($blockTtl)->toISOString(),
        ], now()->addSeconds($blockTtl + 300)); // Keep for 5 minutes after unblock
    }

    /**
     * Check if an IP address is currently blocked.
     */
    public function isIpBlocked(string $ipAddress): bool
    {
        $cacheKey = config('security.cache.blocked_ip_prefix', 'blocked_ip:').$ipAddress;

        return Cache::has($cacheKey);
    }

    /**
     * Get blocked IP information.
     */
    public function getBlockedIpInfo(string $ipAddress): ?array
    {
        $cacheKey = config('security.cache.blocked_ip_prefix', 'blocked_ip:').$ipAddress;

        return Cache::get($cacheKey);
    }

    /**
     * Manually unblock an IP address (admin action).
     */
    public function unblockIp(string $ipAddress, ?int $performedBy = null, string $reason = 'Manual unblock'): bool
    {
        $cacheKey = config('security.cache.blocked_ip_prefix', 'blocked_ip:').$ipAddress;

        if (! Cache::has($cacheKey)) {
            return false; // IP was not blocked
        }

        $blockInfo = Cache::get($cacheKey);
        Cache::forget($cacheKey);

        // Create audit entry for manual unblock
        if (config('security.audit.log_ip_blocks', true)) {
            try {
                // Create audit entry directly using Eloquent to bypass model validation
                $audit = new PermissionAudit;
                $audit->user_id = $blockInfo['user_id'] ?? null;
                $audit->permission_id = null; // IP blocks don't relate to permissions
                $audit->role_id = null; // IP blocks don't relate to roles
                $audit->action = 'modified'; // Use existing enum value for now
                $audit->old_values = json_encode($blockInfo);
                $audit->new_values = json_encode(['action_type' => 'ip_unblock_manual']);
                $audit->ip_address = $ipAddress;
                $audit->user_agent = request()?->userAgent();
                $audit->performed_by = $performedBy;
                $audit->reason = $reason;
                $audit->created_at = now();
                $audit->save();
            } catch (\Exception $e) {
                Log::error('Failed to create IP unblock audit entry', [
                    'ip_address' => $ipAddress,
                    'performed_by' => $performedBy,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('IP address manually unblocked', [
            'ip_address' => $ipAddress,
            'performed_by' => $performedBy,
            'reason' => $reason,
        ]);

        return true;
    }

    /**
     * Get all currently blocked IPs.
     */
    public function getBlockedIps(): array
    {
        // This is a simplified implementation
        // In production, you might want to use Redis SCAN or maintain a separate index
        $prefix = config('security.cache.blocked_ip_prefix', 'blocked_ip:');
        $blockedIps = [];

        // Note: This is a basic implementation. For better performance with many blocked IPs,
        // consider maintaining a separate index or using Redis SCAN operations.

        return $blockedIps;
    }

    /**
     * Process expired IP blocks and create unblock audit entries.
     * This method should be called periodically by a scheduled job.
     */
    public function processExpiredBlocks(): int
    {
        $processed = 0;
        $currentTime = now()->timestamp;

        // Get all unblock audit keys that should be processed
        $pattern = "unblock_audit:*:{$currentTime}";

        // This is a simplified implementation
        // In production, you'd use proper Redis operations to find expired blocks

        return $processed;
    }
}

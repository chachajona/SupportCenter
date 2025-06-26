<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmergencyAccess;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\SecurityLog;

final class EmergencyAccessService
{
    public function __construct(
        private readonly PermissionCacheService $cacheService
    ) {
    }

    /**
     * Grant emergency access to a user.
     */
    public function grantEmergencyAccess(
        int $userId,
        array $permissions,
        string $reason,
        int $durationMinutes = 60,
        ?int $grantedBy = null
    ): EmergencyAccess {
        $emergencyAccess = EmergencyAccess::create([
            'user_id' => $userId,
            'permissions' => $permissions,
            'reason' => $reason,
            'expires_at' => now()->addMinutes($durationMinutes),
            'granted_by' => $grantedBy ?? Auth::id(),
        ]);

        // Clear user cache to immediately apply emergency permissions
        $this->cacheService->clearUserCache($userId);

        // Immediate notification to security team
        $this->notifySecurityTeam($emergencyAccess);

        // Log the emergency access grant
        Log::channel('security')->alert('Emergency access granted', [
            'user_id' => $emergencyAccess->user_id,
            'permissions' => $emergencyAccess->permissions,
            'reason' => $emergencyAccess->reason,
            'granted_by' => $emergencyAccess->granted_by,
            'expires_at' => $emergencyAccess->expires_at,
            'emergency_access_id' => $emergencyAccess->id,
        ]);

        return $emergencyAccess;
    }

    /**
     * Revoke emergency access before expiration.
     */
    public function revokeEmergencyAccess(int $emergencyAccessId, string $reason, ?int $revokedBy = null): bool
    {
        $emergencyAccess = EmergencyAccess::find($emergencyAccessId);

        if (!$emergencyAccess || !$emergencyAccess->isValid()) {
            return false;
        }

        $success = $emergencyAccess->deactivate();

        if ($success) {
            $this->cacheService->clearUserCache($emergencyAccess->user_id);

            Log::channel('security')->info('Emergency access revoked', [
                'emergency_access_id' => $emergencyAccessId,
                'user_id' => $emergencyAccess->user_id,
                'reason' => $reason,
                'revoked_by' => $revokedBy ?? Auth::id(),
            ]);
        }

        return $success;
    }

    /**
     * Mark emergency access as used when first utilized.
     */
    public function markAsUsed(int $emergencyAccessId): bool
    {
        $emergencyAccess = EmergencyAccess::find($emergencyAccessId);

        if (!$emergencyAccess || !$emergencyAccess->isValid()) {
            return false;
        }

        $success = $emergencyAccess->markAsUsed();

        if ($success) {
            Log::channel('security')->warning('Emergency access used', [
                'emergency_access_id' => $emergencyAccessId,
                'user_id' => $emergencyAccess->user_id,
                'used_at' => now(),
            ]);
        }

        return $success;
    }

    /**
     * Get active emergency access for a user.
     */
    public function getUserActiveEmergencyAccess(int $userId): ?EmergencyAccess
    {
        return EmergencyAccess::where('user_id', $userId)
            ->active()
            ->latest()
            ->first();
    }

    /**
     * Check if user has specific emergency permission.
     */
    public function userHasEmergencyPermission(int $userId, string $permission): bool
    {
        $emergencyAccess = $this->getUserActiveEmergencyAccess($userId);

        if (!$emergencyAccess) {
            return false;
        }

        return in_array($permission, $emergencyAccess->permissions);
    }

    /**
     * Clean up expired emergency access records.
     */
    public function cleanupExpiredEmergencyAccess(): int
    {
        $expired = EmergencyAccess::expired()->where('is_active', true)->get();

        foreach ($expired as $emergencyAccess) {
            $emergencyAccess->deactivate();
            $this->cacheService->clearUserCache($emergencyAccess->user_id);

            Log::channel('security')->info('Emergency access expired', [
                'emergency_access_id' => $emergencyAccess->id,
                'user_id' => $emergencyAccess->user_id,
            ]);
        }

        return $expired->count();
    }

    /**
     * Get emergency access statistics for monitoring.
     */
    public function getEmergencyAccessStats(int $days = 30): array
    {
        $since = now()->subDays($days);

        return [
            'total_granted' => EmergencyAccess::where('created_at', '>=', $since)->count(),
            'currently_active' => EmergencyAccess::active()->count(),
            'used_access' => EmergencyAccess::whereNotNull('used_at')
                ->where('created_at', '>=', $since)
                ->count(),
            'expired_access' => EmergencyAccess::expired()
                ->where('created_at', '>=', $since)
                ->count(),
            'most_common_permissions' => $this->getMostCommonEmergencyPermissions($days),
        ];
    }

    /**
     * Get most commonly granted emergency permissions.
     */
    private function getMostCommonEmergencyPermissions(int $days): array
    {
        $since = now()->subDays($days);
        $emergencyAccess = EmergencyAccess::where('created_at', '>=', $since)->get();

        $permissionCounts = [];
        foreach ($emergencyAccess as $access) {
            foreach ($access->permissions as $permission) {
                $permissionCounts[$permission] = ($permissionCounts[$permission] ?? 0) + 1;
            }
        }

        arsort($permissionCounts);
        return array_slice($permissionCounts, 0, 10, true);
    }

    /**
     * Notify security team about emergency access grant.
     */
    private function notifySecurityTeam(EmergencyAccess $emergencyAccess): void
    {
        try {
            // Get system administrators
            $securityTeam = User::role('system_administrator')->get();

            foreach ($securityTeam as $admin) {
                // Check if notification class exists before using it
                if (class_exists(EmergencyAccessGrantedNotification::class)) {
                    $admin->notify(new EmergencyAccessGrantedNotification($emergencyAccess));
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify security team about emergency access', [
                'emergency_access_id' => $emergencyAccess->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get emergency access audit trail.
     */
    public function getEmergencyAccessAuditTrail(int $emergencyAccessId): array
    {
        $emergencyAccess = EmergencyAccess::with(['user', 'grantedBy'])->find($emergencyAccessId);

        if (!$emergencyAccess) {
            return [];
        }

        return [
            'id' => $emergencyAccess->id,
            'user' => [
                'id' => $emergencyAccess->user->id,
                'name' => $emergencyAccess->user->name,
                'email' => $emergencyAccess->user->email,
            ],
            'granted_by' => [
                'id' => $emergencyAccess->grantedBy->id,
                'name' => $emergencyAccess->grantedBy->name,
                'email' => $emergencyAccess->grantedBy->email,
            ],
            'permissions' => $emergencyAccess->permissions,
            'reason' => $emergencyAccess->reason,
            'granted_at' => $emergencyAccess->granted_at,
            'expires_at' => $emergencyAccess->expires_at,
            'used_at' => $emergencyAccess->used_at,
            'is_active' => $emergencyAccess->is_active,
            'remaining_time' => $emergencyAccess->remaining_time,
        ];
    }

    /**
     * Generate break-glass emergency access with one-time token
     */
    public function generateBreakGlass(User $user, array $permissions, string $reason, int $durationMinutes = 10): array
    {
        $emergencyAccess = EmergencyAccess::create([
            'user_id' => $user->id,
            'permissions' => $permissions,
            'reason' => $reason,
            'granted_by' => auth()->id(),
            'expires_at' => now()->addMinutes($durationMinutes),
        ]);

        $token = $emergencyAccess->generateBreakGlassToken();

        // Fire security event for audit
        SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => \App\Enums\SecurityEventType::EMERGENCY_ACCESS,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'details' => [
                'type' => 'break_glass_generated',
                'granted_by' => auth()->id(),
                'permissions' => $permissions,
                'expires_at' => $emergencyAccess->expires_at,
            ],
        ]);

        return [
            'token' => $token,
            'expires_at' => $emergencyAccess->expires_at,
            'emergency_access_id' => $emergencyAccess->id,
        ];
    }
}

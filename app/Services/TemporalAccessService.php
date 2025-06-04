<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PermissionAudit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

final class TemporalAccessService
{
    public function __construct(
        private readonly PermissionCacheService $cacheService
    ) {
    }

    /**
     * Grant a temporary role to a user.
     */
    public function grantTemporaryRole(
        int $userId,
        int $roleId,
        int $durationMinutes,
        string $reason,
        int $grantedBy
    ): bool {
        // Validate inputs and permissions
        $this->validateTemporaryRoleRequest($userId, $roleId, $grantedBy);

        try {
            return DB::transaction(function () use ($userId, $roleId, $durationMinutes, $reason, $grantedBy) {
                $expiresAt = now()->addMinutes($durationMinutes);
                $grantedAt = now();

                DB::table('role_user')->insert([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'granted_by' => $grantedBy,
                    'granted_at' => $grantedAt,
                    'expires_at' => $expiresAt,
                    'delegation_reason' => $reason,
                    'is_active' => true,
                    'created_at' => $grantedAt,
                    'updated_at' => $grantedAt,
                ]);

                // Clear user cache to immediately apply new role
                $this->cacheService->clearUserCache($userId);

                // Create audit record
                PermissionAudit::logPermissionChange(
                    userId: $userId,
                    permissionId: null,
                    roleId: $roleId,
                    action: 'granted',
                    newValues: [
                        'expires_at' => $expiresAt,
                        'granted_at' => $grantedAt,
                        'reason' => $reason,
                        'duration_minutes' => $durationMinutes,
                    ],
                    reason: $reason,
                    performedBy: $grantedBy
                );

                Log::info('Temporary role granted', [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'expires_at' => $expiresAt,
                    'granted_by' => $grantedBy,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to grant temporary role', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Extend the expiration of a temporary role.
     */
    public function extendTemporaryRole(
        int $userId,
        int $roleId,
        int $additionalMinutes,
        string $reason,
        int $extendedBy
    ): bool {
        try {
            // Get current expires_at and calculate new expiration to avoid SQL injection
            $roleAssignment = DB::table('role_user')
                ->where('user_id', $userId)
                ->where('role_id', $roleId)
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->first(['expires_at']);

            if (!$roleAssignment) {
                return false;
            }

            $newExpiresAt = \Carbon\Carbon::parse($roleAssignment->expires_at)
                ->addMinutes($additionalMinutes);

            $updated = DB::table('role_user')
                ->where('user_id', $userId)
                ->where('role_id', $roleId)
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->update([
                    'expires_at' => $newExpiresAt,
                    'updated_at' => now(),
                ]);

            if ($updated) {
                $this->cacheService->clearUserCache($userId);

                PermissionAudit::logPermissionChange(
                    userId: $userId,
                    permissionId: null,
                    roleId: $roleId,
                    action: 'modified',
                    newValues: [
                        'additional_minutes' => $additionalMinutes,
                        'new_expires_at' => $newExpiresAt,
                        'reason' => $reason,
                    ],
                    reason: $reason,
                    performedBy: $extendedBy
                );

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to extend temporary role', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Revoke a temporary role before its expiration.
     */
    public function revokeTemporaryRole(
        int $userId,
        int $roleId,
        string $reason,
        int $revokedBy
    ): bool {
        try {
            $updated = DB::table('role_user')
                ->where('user_id', $userId)
                ->where('role_id', $roleId)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);

            if ($updated) {
                $this->cacheService->clearUserCache($userId);

                PermissionAudit::logPermissionChange(
                    userId: $userId,
                    permissionId: null,
                    roleId: $roleId,
                    action: 'revoked',
                    newValues: ['reason' => $reason],
                    reason: $reason,
                    performedBy: $revokedBy
                );

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to revoke temporary role', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Clean up expired role assignments with optimized batch operations.
     */
    public function cleanupExpiredPermissions(): int
    {
        try {
            // Get expired assignments for audit logging before batch update
            $expired = DB::table('role_user')
                ->where('expires_at', '<', now())
                ->where('is_active', true)
                ->select('id', 'user_id', 'role_id')
                ->get();

            if ($expired->isEmpty()) {
                return 0;
            }

            // Perform batch update for better performance
            $updatedCount = DB::table('role_user')
                ->where('expires_at', '<', now())
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);

            // Clear cache for affected users and create audit logs
            $affectedUserIds = $expired->pluck('user_id')->unique();

            foreach ($affectedUserIds as $userId) {
                $this->cacheService->clearUserCache($userId);
            }

            // Create audit logs for each expired assignment
            foreach ($expired as $assignment) {
                PermissionAudit::logPermissionChange(
                    userId: $assignment->user_id,
                    permissionId: null,
                    roleId: $assignment->role_id,
                    action: 'revoked',
                    newValues: ['reason' => 'Automatic expiration'],
                    reason: 'Automatic expiration'
                );
            }

            if ($updatedCount > 0) {
                Log::info('Cleaned up expired role assignments', [
                    'count' => $updatedCount,
                ]);
            }

            return $updatedCount;
        } catch (\Exception $e) {
            Log::error('Failed to cleanup expired permissions', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get active temporary roles for a user.
     */
    public function getUserActiveTemporaryRoles(int $userId): array
    {
        return DB::table('role_user')
            ->join('roles', 'role_user.role_id', '=', 'roles.id')
            ->where('role_user.user_id', $userId)
            ->where('role_user.is_active', true)
            ->whereNotNull('role_user.expires_at')
            ->where('role_user.expires_at', '>', now())
            ->select([
                'roles.name',
                'roles.display_name',
                'role_user.expires_at',
                'role_user.delegation_reason',
                'role_user.granted_at',
            ])
            ->get()
            ->toArray();
    }

    /**
     * Get expiring roles that need attention.
     */
    public function getExpiringRoles(int $withinMinutes = 60): array
    {
        $threshold = now()->addMinutes($withinMinutes);

        return DB::table('role_user')
            ->join('roles', 'role_user.role_id', '=', 'roles.id')
            ->join('users', 'role_user.user_id', '=', 'users.id')
            ->where('role_user.is_active', true)
            ->where('role_user.expires_at', '<=', $threshold)
            ->where('role_user.expires_at', '>', now())
            ->select([
                'users.name as user_name',
                'users.email as user_email',
                'roles.name as role_name',
                'role_user.expires_at',
                'role_user.delegation_reason',
            ])
            ->get()
            ->toArray();
    }

    /**
     * Bulk cleanup of all expired permissions and roles.
     * Removed redundant cache clearing since cleanupExpiredPermissions() handles it.
     */
    public function bulkCleanupExpired(): array
    {
        $rolesCleanedCount = $this->cleanupExpiredPermissions();

        return [
            'roles_cleaned' => $rolesCleanedCount,
            'cache_cleared' => $rolesCleanedCount > 0 ? 'handled_by_cleanup_method' : 0,
        ];
    }

    /**
     * Validate temporary role request parameters and permissions.
     */
    private function validateTemporaryRoleRequest(int $userId, int $roleId, int $grantedBy): void
    {
        // Validate user exists
        if (!User::find($userId)) {
            throw new InvalidArgumentException("User with ID {$userId} does not exist");
        }

        // Validate role exists and is active
        $role = Role::find($roleId);
        if (!$role) {
            throw new InvalidArgumentException("Role with ID {$roleId} does not exist");
        }

        if (!$role->is_active) {
            throw new InvalidArgumentException("Role '{$role->name}' is inactive and cannot be assigned");
        }

        // Validate granter exists
        $granter = User::find($grantedBy);
        if (!$granter) {
            throw new InvalidArgumentException("Granter with ID {$grantedBy} does not exist");
        }

        // Basic permission check - granter should have permission to assign roles
        // This is a simplified check; implement more complex authorization as needed
        if (!$granter->hasAnyRole(['system_administrator', 'department_manager'])) {
            throw new RuntimeException("User {$grantedBy} does not have permission to grant temporary roles");
        }
    }
}

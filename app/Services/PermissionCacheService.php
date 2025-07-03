<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class PermissionCacheService
{
    private readonly string $cachePrefix;

    private readonly int $cacheTtl;

    public function __construct()
    {
        $this->cachePrefix = 'rbac:';
        $this->cacheTtl = 3600; // 1 hour
    }

    /**
     * Get all permissions for a user from cache or database.
     */
    public function getUserPermissions(int $userId): Collection
    {
        $key = $this->cachePrefix."user:{$userId}:permissions";

        if (Cache::supportsTags()) {
            return Cache::tags(['permissions', "user:{$userId}"])
                ->remember($key, $this->cacheTtl, function () use ($userId): Collection {
                    return $this->loadUserPermissions($userId);
                });
        } else {
            return Cache::remember($key, $this->cacheTtl, function () use ($userId): Collection {
                return $this->loadUserPermissions($userId);
            });
        }
    }

    /**
     * Get all roles for a user from cache or database.
     */
    public function getUserRoles(int $userId): Collection
    {
        $key = $this->cachePrefix."user:{$userId}:roles";

        if (Cache::supportsTags()) {
            return Cache::tags(['roles', "user:{$userId}"])
                ->remember($key, $this->cacheTtl, function () use ($userId): Collection {
                    return $this->loadUserRoles($userId);
                });
        } else {
            return Cache::remember($key, $this->cacheTtl, function () use ($userId): Collection {
                return $this->loadUserRoles($userId);
            });
        }
    }

    /**
     * Load user permissions from database.
     */
    private function loadUserPermissions(int $userId): Collection
    {
        $user = User::find($userId);
        if (! $user) {
            return collect();
        }

        return $user->getAllPermissions()->pluck('name');
    }

    /**
     * Load user roles from database.
     */
    private function loadUserRoles(int $userId): Collection
    {
        $user = User::find($userId);
        if (! $user) {
            return collect();
        }

        return $user->roles()->where('roles.is_active', true)->pluck('name');
    }

    /**
     * Check if user has a specific permission.
     */
    public function userHasPermission(int $userId, string $permission): bool
    {
        $permissions = $this->getUserPermissions($userId);

        return $permissions->contains($permission);
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function userHasAnyPermission(int $userId, array $permissions): bool
    {
        $userPermissions = $this->getUserPermissions($userId);

        return $userPermissions->intersect($permissions)->isNotEmpty();
    }

    /**
     * Check if user has all of the given permissions.
     */
    public function userHasAllPermissions(int $userId, array $permissions): bool
    {
        $userPermissions = $this->getUserPermissions($userId);

        return collect($permissions)->every(fn ($permission) => $userPermissions->contains($permission));
    }

    /**
     * Check if user has a specific role.
     */
    public function userHasRole(int $userId, string $role): bool
    {
        $roles = $this->getUserRoles($userId);

        return $roles->contains($role);
    }

    /**
     * Check if user has any of the given roles.
     */
    public function userHasAnyRole(int $userId, array $roles): bool
    {
        $userRoles = $this->getUserRoles($userId);

        return $userRoles->intersect($roles)->isNotEmpty();
    }

    /**
     * Clear all cache for a specific user.
     */
    public function clearUserCache(int $userId): void
    {
        if (Cache::supportsTags()) {
            Cache::tags(["user:{$userId}"])->flush();
        } else {
            // Clear individual cache keys when tags aren't supported
            Cache::forget($this->cachePrefix."user:{$userId}:permissions");
            Cache::forget($this->cachePrefix."user:{$userId}:roles");
        }
    }

    /**
     * Clear all permission-related cache.
     */
    public function clearAllCache(): void
    {
        if (Cache::supportsTags()) {
            Cache::tags(['permissions', 'roles'])->flush();
        } else {
            // When tags aren't supported, we can't easily clear all related cache
            // This would require keeping track of all user IDs, which is not practical
            // Consider using a cache store that supports tagging for production
        }
    }

    /**
     * Pre-warm cache for a user by loading their permissions and roles.
     */
    public function warmUserCache(int $userId): void
    {
        $this->clearUserCache($userId);
        $this->getUserPermissions($userId);
        $this->getUserRoles($userId);
    }

    /**
     * Get cache statistics for monitoring.
     */
    public function getCacheStats(): array
    {
        $cacheKey = config('cache.prefix', '').':';

        return [
            'store' => config('cache.default'),
            'prefix' => $this->cachePrefix,
            'ttl' => $this->cacheTtl,
            'tags_supported' => Cache::supportsTags(),
        ];
    }

    /**
     * Batch warm cache for multiple users.
     */
    public function warmBatchUserCache(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $this->warmUserCache($userId);
        }
    }
}

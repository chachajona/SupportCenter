<?php

namespace App\Http\Middleware;

use App\Models\PermissionAudit;
use App\Services\PermissionCacheService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RolePermissionMiddleware
{
    protected PermissionCacheService $permissionCache;

    public function __construct(PermissionCacheService $permissionCache)
    {
        $this->permissionCache = $permissionCache;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if (! $user) {
            $this->logUnauthorizedAttempt($request, 'unauthenticated', $permissions);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Rate limiting for permission checks per user
        $rateLimitKey = "permission-check:{$user->id}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, 100)) {
            Log::warning('Rate limit exceeded for permission checks', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route' => $request->route()?->getName(),
            ]);

            $this->auditUnauthorizedAccess($user, $request, $permissions, 'rate_limit_exceeded');

            return response()->json(['error' => 'Too many requests'], 429);
        }

        RateLimiter::hit($rateLimitKey, 60); // 60 seconds window

        // Check permissions with caching
        $startTime = microtime(true);
        $hasPermission = $this->checkUserPermissions($user->id, $permissions);
        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Log performance if slow
        if ($executionTime > 10) {
            Log::warning('Slow permission check detected', [
                'user_id' => $user->id,
                'permissions' => $permissions,
                'execution_time_ms' => $executionTime,
                'route' => $request->route()?->getName(),
            ]);
        }

        if (! $hasPermission) {
            $this->auditUnauthorizedAccess($user, $request, $permissions, 'insufficient_permissions');

            return response()->json([
                'error' => 'Insufficient permissions',
                'required_permissions' => $permissions,
                'message' => 'You do not have the required permissions to access this resource.',
            ], 403);
        }

        // Log successful access for audit purposes
        if (config('rbac.log_successful_access', false)) {
            Log::info('Successful permission check', [
                'user_id' => $user->id,
                'permissions' => $permissions,
                'route' => $request->route()?->getName(),
                'execution_time_ms' => $executionTime,
            ]);
        }

        return $next($request);
    }

    /**
     * Check if user has any of the required permissions
     */
    protected function checkUserPermissions(int $userId, array $permissions): bool
    {
        // Handle wildcard permissions
        foreach ($permissions as $permission) {
            if (str_contains($permission, '*')) {
                if ($this->checkWildcardPermission($userId, $permission)) {
                    return true;
                }
            } else {
                if ($this->permissionCache->userHasPermission($userId, $permission)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check wildcard permissions
     */
    protected function checkWildcardPermission(int $userId, string $wildcardPermission): bool
    {
        $pattern = str_replace('*', '.*', preg_quote($wildcardPermission, '/'));
        $userPermissions = $this->permissionCache->getUserPermissions($userId);

        foreach ($userPermissions as $permission) {
            if (preg_match("/^{$pattern}$/", $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Audit unauthorized access attempts
     */
    protected function auditUnauthorizedAccess($user, Request $request, array $permissions, string $reason): void
    {
        try {
            PermissionAudit::create([
                'user_id' => $user->id,
                'action' => 'unauthorized_access_attempt',
                'new_values' => [
                    'required_permissions' => $permissions,
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'url' => $request->url(),
                    'reason' => $reason,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'reason' => "Unauthorized access attempt: {$reason}",
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create permission audit record', [
                'user_id' => $user->id,
                'permissions' => $permissions,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log unauthorized attempts for unauthenticated users
     */
    protected function logUnauthorizedAttempt(Request $request, string $reason, array $permissions): void
    {
        Log::warning('Unauthorized access attempt', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'url' => $request->url(),
            'required_permissions' => $permissions,
            'reason' => $reason,
        ]);
    }
}

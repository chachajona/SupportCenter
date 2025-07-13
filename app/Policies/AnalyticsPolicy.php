<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

/**
 * Policy for managing analytics access permissions.
 */
final class AnalyticsPolicy
{
    /**
     * Determine whether the user can view any analytics.
     */
    public function viewAny(User $user): bool
    {
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'analytics_view_any', null);

            return true;
        }

        return $user->hasAnyPermission([
            'analytics.view_department_analytics',
            'analytics.view_all_analytics',
        ]);
    }

    /**
     * Determine whether the user can view department analytics.
     */
    public function viewDepartmentAnalytics(User $user, Department $department): bool
    {
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'analytics_view_department', $department);

            return true;
        }

        // Must have permission to view department analytics
        if (!$user->hasPermissionTo('analytics.view_department_analytics')) {
            return false;
        }

        // Check department access
        return $user->hasDepartmentAccess($department->id);
    }

    /**
     * Determine whether the user can view all analytics.
     */
    public function viewAllAnalytics(User $user): bool
    {
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'analytics_view_all', null);

            return true;
        }

        return $user->hasPermissionTo('analytics.view_all_analytics');
    }

    /**
     * Determine whether the user can perform a permission-gated analytics action which may be scoped to a
     * specific department or to all departments.
     *
     * @param  User                $user               The user requesting the action.
     * @param  string              $requiredPermission The base permission required (e.g. "analytics.export_reports").
     * @param  string              $emergencyAction    Action string to log when emergency access is used.
     * @param  Department|null     $department         Optional department context.
     */
    private function canPerformScopedAction(
        User $user,
        string $requiredPermission,
        string $emergencyAction,
        ?Department $department = null,
    ): bool {
        // Emergency access overrides all checks
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, $emergencyAction, $department);

            return true;
        }

        // Verify the specific action permission
        if (!$user->hasPermissionTo($requiredPermission)) {
            return false;
        }

        // If scoped to a department, ensure the user has access to that department
        if ($department !== null) {
            return $user->hasDepartmentAccess($department->id);
        }

        // For global actions, user must also have permission to view all analytics
        return $user->hasPermissionTo('analytics.view_all_analytics');
    }

    /**
     * Determine whether the user can export reports.
     */
    public function exportReports(User $user, ?Department $department = null): bool
    {
        return $this->canPerformScopedAction(
            $user,
            'analytics.export_reports',
            'analytics_export',
            $department,
        );
    }

    /**
     * Determine whether the user can schedule reports.
     */
    public function scheduleReports(User $user, ?Department $department = null): bool
    {
        return $this->canPerformScopedAction(
            $user,
            'analytics.schedule_reports',
            'analytics_schedule',
            $department,
        );
    }

    /**
     * Audit emergency access usage.
     */
    private function auditEmergencyAccess(User $user, string $action, ?Department $department): void
    {
        try {
            \App\Models\PermissionAudit::create([
                'user_id' => $user->getKey(),
                'action' => 'emergency_access_used',
                'old_values' => null,
                'new_values' => [
                    'action' => $action,
                    'department_id' => $department?->getKey(),
                    'emergency_access_id' => $user->getActiveEmergencyAccess()?->getKey(),
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent() ?? '',
                'performed_by' => $user->getKey(),
                'reason' => 'Emergency access used for analytics operation',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Gracefully handle failures (e.g., database write issues)
            \Illuminate\Support\Facades\Log::error('Failed to create permission audit record', [
                'user_id' => $user->getKey(),
                'exception' => $e,
            ]);
        }
    }
}

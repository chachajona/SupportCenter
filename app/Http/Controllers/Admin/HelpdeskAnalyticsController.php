<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\HelpdeskAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

final class HelpdeskAnalyticsController extends Controller
{
    public function __construct(
        private readonly HelpdeskAnalyticsService $analyticsService
    ) {}

    /**
     * Display the helpdesk analytics dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $timeRange = $request->get('range', '7d');

        // Determine accessible departments based on user permissions
        $departmentIds = $this->getAccessibleDepartments($user);

        // Get analytics data
        $analytics = $this->analyticsService->getDashboardMetrics($departmentIds);
        $recentActivity = $this->analyticsService->getRecentActivity($departmentIds, 15);

        // Get available departments for filtering
        $departments = $this->getAvailableDepartments($user);

        return inertia('admin/helpdesk-analytics/index', [
            'analytics' => $analytics,
            'recentActivity' => $recentActivity,
            'departments' => $departments,
            'timeRange' => $timeRange,
            'userPermissions' => [
                'can_view_all_departments' => $user->hasPermissionTo('analytics.view_all'),
                'can_export' => $user->hasPermissionTo('analytics.export'),
                'accessible_departments' => $departmentIds,
            ],
        ]);
    }

    /**
     * Get real-time metrics for dashboard updates.
     */
    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();
        $departmentIds = $this->getAccessibleDepartments($user);

        $overview = $this->analyticsService->getDashboardMetrics($departmentIds)['overview'];
        $recentActivity = $this->analyticsService->getRecentActivity($departmentIds, 5);

        return response()->json([
            'overview' => $overview,
            'recent_activity' => $recentActivity,
            'timestamp' => Carbon::now()->toISOString(),
        ]);
    }

    /**
     * Export analytics data.
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermissionTo('analytics.export')) {
            abort(403, 'Insufficient permissions to export analytics data.');
        }

        $timeRange = $request->get('range', '30d');
        $departmentIds = $this->getAccessibleDepartments($user);

        $analytics = $this->analyticsService->getDashboardMetrics($departmentIds);

        $exportData = [
            'exported_at' => Carbon::now()->toISOString(),
            'exported_by' => $user->name,
            'time_range' => $timeRange,
            'scope' => $departmentIds ? 'Filtered Departments' : 'All Departments',
            'data' => $analytics,
        ];

        $filename = 'helpdesk-analytics-'.Carbon::now()->format('Y-m-d-H-i').'.json';

        return response()->json($exportData)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename=\"$filename\"");
    }

    /**
     * Get department-specific analytics.
     */
    public function departmentMetrics(Request $request, int $departmentId): JsonResponse
    {
        $user = $request->user();

        // Check if user has access to this department
        $accessibleDepartments = $this->getAccessibleDepartments($user);

        if ($accessibleDepartments !== null && ! in_array($departmentId, $accessibleDepartments)) {
            abort(403, 'Insufficient permissions to view this department\'s analytics.');
        }

        $analytics = $this->analyticsService->getDashboardMetrics([$departmentId]);
        $recentActivity = $this->analyticsService->getRecentActivity([$departmentId], 10);

        return response()->json([
            'analytics' => $analytics,
            'recent_activity' => $recentActivity,
            'department_id' => $departmentId,
        ]);
    }

    /**
     * Get available departments based on user permissions.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function getAvailableDepartments($user)
    {
        if ($user->hasPermissionTo('analytics.view_all')) {
            return Department::select('id', 'name', 'description')
                ->orderBy('name')
                ->get()
                ->map(fn ($dept) => ['id' => $dept->id, 'name' => $dept->name, 'description' => $dept->description]);
        }

        if ($user->hasPermissionTo('analytics.view_department')) {
            return Department::select('id', 'name', 'description')
                ->where('id', $user->department_id)
                ->orWhereIn('id', $user->getAccessibleDepartmentIds())
                ->orderBy('name')
                ->get()
                ->map(fn ($dept) => ['id' => $dept->id, 'name' => $dept->name, 'description' => $dept->description]);
        }

        return collect();
    }

    /**
     * Get accessible department IDs based on user permissions.
     *
     * @return array<int>|null
     */
    private function getAccessibleDepartments($user): ?array
    {
        // System administrators and regional managers can see all departments
        if ($user->hasPermissionTo('analytics.view_all')) {
            return null; // null means all departments
        }

        // Department managers can see their department and sub-departments
        if ($user->hasPermissionTo('analytics.view_department')) {
            $departmentIds = [];

            // Add user's own department
            if ($user->department_id) {
                $departmentIds[] = $user->department_id;
            }

            // Add accessible departments from RBAC
            $departmentIds = array_merge($departmentIds, $user->getAccessibleDepartmentIds());

            return array_unique($departmentIds);
        }

        // Support agents with view_own permission see no department-level analytics
        // They would only see their own ticket metrics in a different view
        return [];
    }
}

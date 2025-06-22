<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PermissionAudit;
use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $query = PermissionAudit::with([
            'user:id,name,email',
            'permission:id,name,display_name',
            'role:id,name,display_name',
            'performedBy:id,name,email'
        ]);

        // Apply filters
        if ($request->filled('user')) {
            $userSearch = $request->user;
            $query->whereHas('user', function ($q) use ($userSearch) {
                $q->where('name', 'like', "%{$userSearch}%")
                    ->orWhere('email', 'like', "%{$userSearch}%");
            });
        }

        if ($request->filled('action')) {
            $query->action($request->action);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        if ($request->filled('performed_by')) {
            $performerSearch = $request->performed_by;
            $query->whereHas('performedBy', function ($q) use ($performerSearch) {
                $q->where('name', 'like', "%{$performerSearch}%")
                    ->orWhere('email', 'like', "%{$performerSearch}%");
            });
        }

        if ($request->filled('permission')) {
            $permissionSearch = $request->permission;
            $query->whereHas('permission', function ($q) use ($permissionSearch) {
                $q->where('name', 'like', "%{$permissionSearch}%")
                    ->orWhere('display_name', 'like', "%{$permissionSearch}%");
            });
        }

        if ($request->filled('role')) {
            $roleSearch = $request->role;
            $query->whereHas('role', function ($q) use ($roleSearch) {
                $q->where('name', 'like', "%{$roleSearch}%")
                    ->orWhere('display_name', 'like', "%{$roleSearch}%");
            });
        }

        // Order by most recent first
        $query->orderBy('created_at', 'desc');

        // Paginate results
        $perPage = $request->get('per_page', 15);
        $audits = $query->paginate($perPage);

        // Transform data for frontend
        $audits->getCollection()->transform(function ($audit) {
            return [
                'id' => $audit->id,
                'user_id' => $audit->user_id,
                'user' => $audit->user ? [
                    'id' => $audit->user->id,
                    'name' => $audit->user->name,
                    'email' => $audit->user->email
                ] : null,
                'permission_id' => $audit->permission_id,
                'permission' => $audit->permission ? [
                    'id' => $audit->permission->id,
                    'name' => $audit->permission->name,
                    'display_name' => $audit->permission->display_name
                ] : null,
                'role_id' => $audit->role_id,
                'role' => $audit->role ? [
                    'id' => $audit->role->id,
                    'name' => $audit->role->name,
                    'display_name' => $audit->role->display_name
                ] : null,
                'action' => $audit->action,
                'old_values' => $audit->old_values,
                'new_values' => $audit->new_values,
                'ip_address' => $audit->ip_address,
                'user_agent' => $audit->user_agent,
                'performed_by' => $audit->performed_by,
                'performed_by_user' => $audit->performedBy ? [
                    'id' => $audit->performedBy->id,
                    'name' => $audit->performedBy->name,
                    'email' => $audit->performedBy->email
                ] : null,
                'reason' => $audit->reason,
                'created_at' => $audit->created_at->toISOString(),
                'description' => $audit->description,
            ];
        });

        // Calculate statistics
        $stats = $this->calculateAuditStats();

        $filters = $request->only([
            'user',
            'action',
            'date_from',
            'date_to',
            'performed_by',
            'permission',
            'role'
        ]);

        return Inertia::render('admin/audit/index', [
            'audits' => $audits->items(),
            'stats' => $stats,
            'filters' => $filters,
            'pagination' => [
                'current_page' => $audits->currentPage(),
                'last_page' => $audits->lastPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
                'from' => $audits->firstItem(),
                'to' => $audits->lastItem(),
            ],
        ]);
    }

    public function show($id)
    {
        $audit = PermissionAudit::with([
            'user:id,name,email',
            'permission:id,name,display_name,description',
            'role:id,name,display_name,description',
            'performedBy:id,name,email'
        ])->findOrFail($id);

        return response()->json([
            'id' => $audit->id,
            'user_id' => $audit->user_id,
            'user' => $audit->user ? [
                'id' => $audit->user->id,
                'name' => $audit->user->name,
                'email' => $audit->user->email
            ] : null,
            'permission_id' => $audit->permission_id,
            'permission' => $audit->permission ? [
                'id' => $audit->permission->id,
                'name' => $audit->permission->name,
                'display_name' => $audit->permission->display_name,
                'description' => $audit->permission->description
            ] : null,
            'role_id' => $audit->role_id,
            'role' => $audit->role ? [
                'id' => $audit->role->id,
                'name' => $audit->role->name,
                'display_name' => $audit->role->display_name,
                'description' => $audit->role->description
            ] : null,
            'action' => $audit->action,
            'old_values' => $audit->old_values,
            'new_values' => $audit->new_values,
            'ip_address' => $audit->ip_address,
            'user_agent' => $audit->user_agent,
            'performed_by' => $audit->performed_by,
            'performed_by_user' => $audit->performedBy ? [
                'id' => $audit->performedBy->id,
                'name' => $audit->performedBy->name,
                'email' => $audit->performedBy->email
            ] : null,
            'reason' => $audit->reason,
            'created_at' => $audit->created_at->toISOString(),
            'description' => $audit->description,
        ]);
    }

    public function export(Request $request)
    {
        $query = PermissionAudit::with([
            'user:id,name,email',
            'permission:id,name,display_name',
            'role:id,name,display_name',
            'performedBy:id,name,email'
        ]);

        // Apply same filters as index
        if ($request->filled('user')) {
            $userSearch = $request->user;
            $query->whereHas('user', function ($q) use ($userSearch) {
                $q->where('name', 'like', "%{$userSearch}%")
                    ->orWhere('email', 'like', "%{$userSearch}%");
            });
        }

        if ($request->filled('action')) {
            $query->action($request->action);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        // Order by most recent first
        $query->orderBy('created_at', 'desc');

        // Limit export to prevent memory issues
        $audits = $query->limit(10000)->get();

        $filename = 'audit-log-' . Carbon::now()->format('Y-m-d-H-i') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($audits) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'ID',
                'User ID',
                'User Name',
                'User Email',
                'Action',
                'Permission/Role Type',
                'Permission/Role Name',
                'Old Values',
                'New Values',
                'IP Address',
                'User Agent',
                'Performed By ID',
                'Performed By Name',
                'Performed By Email',
                'Reason',
                'Timestamp'
            ]);

            // Data rows
            foreach ($audits as $audit) {
                $resourceType = $audit->permission ? 'Permission' : ($audit->role ? 'Role' : 'Unknown');
                $resourceName = $audit->permission ? $audit->permission->name : ($audit->role ? $audit->role->name : '');

                fputcsv($file, [
                    $audit->id,
                    $audit->user_id,
                    $audit->user?->name ?? '',
                    $audit->user?->email ?? '',
                    $audit->action,
                    $resourceType,
                    $resourceName,
                    json_encode($audit->old_values),
                    json_encode($audit->new_values),
                    $audit->ip_address,
                    $audit->user_agent,
                    $audit->performed_by,
                    $audit->performedBy?->name ?? '',
                    $audit->performedBy?->email ?? '',
                    $audit->reason,
                    $audit->created_at->toDateTimeString()
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function calculateAuditStats(): array
    {
        $totalEvents = PermissionAudit::count();
        $recentEvents = PermissionAudit::recent(7)->count();

        // High risk events (unauthorized attempts, admin actions)
        $highRiskEvents = PermissionAudit::where(function ($query) {
            $query->where('action', 'unauthorized_access_attempt')
                ->orWhere('reason', 'like', '%emergency%')
                ->orWhere('reason', 'like', '%admin%');
        })->recent(30)->count();

        // Active users (users who had permission changes in last 30 days)
        $activeUsers = PermissionAudit::select('user_id')
            ->distinct()
            ->recent(30)
            ->count();

        // Action breakdown for last 30 days
        $actionBreakdown = PermissionAudit::recent(30)
            ->select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        return [
            'total_events' => $totalEvents,
            'recent_events' => $recentEvents,
            'high_risk_events' => $highRiskEvents,
            'active_users' => $activeUsers,
            'action_breakdown' => $actionBreakdown,
            'period' => '30 days',
        ];
    }
}

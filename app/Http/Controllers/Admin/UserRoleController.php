<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\PermissionAudit;
use App\Services\TemporalAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserRoleController extends Controller
{
    public function __construct(
        private readonly TemporalAccessService $temporalAccessService
    ) {
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        $role = $request->get('role');
        $department = $request->get('department');

        $users = User::query()
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($role, function ($query, $role) {
                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('roles.id', $role)
                        ->where('role_user.is_active', true);
                });
            })
            ->when($department, function ($query, $department) {
                $query->where('department_id', $department);
            })
            ->with([
                'roles' => function ($query) {
                    $query->where('role_user.is_active', true)
                        ->withPivot(['granted_at', 'expires_at', 'granted_by']);
                },
                'department:id,name'
            ])
            ->orderBy('name')
            ->paginate(20);

        $roles = Role::where('is_active', true)->orderBy('display_name')->get();

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'role' => $role,
                'department' => $department,
            ],
        ]);
    }

    public function show(User $user)
    {
        $user->load([
            'roles' => function ($query) {
                $query->withPivot(['granted_at', 'expires_at', 'granted_by', 'delegation_reason', 'is_active'])
                    ->with('permissions:id,name,display_name,resource');
            },
            'department:id,name'
        ]);

        // Get available roles for assignment
        $availableRoles = Role::where('is_active', true)
            ->whereNotIn('id', $user->roles->pluck('id'))
            ->orderBy('hierarchy_level')
            ->orderBy('display_name')
            ->get();

        // Get recent role changes for this user
        $recentAudits = PermissionAudit::where('user_id', $user->id)
            ->whereNotNull('role_id')
            ->with(['role:id,name,display_name', 'performedByUser:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return Inertia::render('admin/users/show', [
            'user' => $user,
            'availableRoles' => $availableRoles,
            'recentAudits' => $recentAudits,
        ]);
    }

    public function assign(Request $request, User $user)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $role = Role::findOrFail($request->role_id);

        // Check if user already has this role
        if ($user->hasRole($role)) {
            return response()->json([
                'message' => 'User already has this role assigned',
            ], 422);
        }

        try {
            DB::transaction(function () use ($request, $user, $role) {
                $user->assignRole($role);

                // Audit the assignment
                PermissionAudit::create([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'action' => 'granted',
                    'new_values' => [
                        'role_name' => $role->display_name,
                        'user_name' => $user->name,
                        'user_email' => $user->email,
                    ],
                    'performed_by' => Auth::id(),
                    'reason' => $request->reason ?? "Role assigned: {$role->display_name} to {$user->name}",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });

            return response()->json([
                'message' => 'Role assigned successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to assign role: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function revoke(Request $request, User $user, Role $role)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        // Check if user has this role
        if (!$user->hasRole($role)) {
            return response()->json([
                'message' => 'User does not have this role',
            ], 422);
        }

        // Prevent revoking system administrator role if this is the last admin
        if ($role->name === 'system_administrator') {
            $adminCount = User::role('system_administrator')->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'message' => 'Cannot revoke the last system administrator role',
                ], 422);
            }
        }

        try {
            DB::transaction(function () use ($request, $user, $role) {
                $user->removeRole($role);

                // Audit the revocation
                PermissionAudit::create([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'action' => 'revoked',
                    'old_values' => [
                        'role_name' => $role->display_name,
                        'user_name' => $user->name,
                        'user_email' => $user->email,
                    ],
                    'performed_by' => Auth::id(),
                    'reason' => $request->reason ?? "Role revoked: {$role->display_name} from {$user->name}",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });

            return response()->json([
                'message' => 'Role revoked successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to revoke role: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function assignTemporary(Request $request, User $user)
    {
        $request->validate([
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => 'integer|exists:roles,id',
            'duration' => 'required|integer|min:1|max:1440', // up to 24 hours
            'duration_unit' => 'required|in:minutes,hours,days',
            'reason' => 'required|string|min:10|max:500',
            'emergency' => 'sometimes|boolean',
        ]);

        // Convert duration to minutes based on provided unit
        $durationMinutes = match ($request->duration_unit) {
            'minutes' => (int) $request->duration,
            'hours' => (int) $request->duration * 60,
            'days' => (int) $request->duration * 1440,
            default => 0,
        };

        if ($durationMinutes < 1) {
            return response()->json([
                'message' => 'Invalid duration',
            ], 422);
        }

        $assigned = [];
        $skipped = [];

        foreach ($request->role_ids as $roleId) {
            $role = Role::find($roleId);

            if (!$role) {
                $skipped[] = [
                    'role_id' => $roleId,
                    'reason' => 'Role not found',
                ];
                continue;
            }

            if ($user->hasRole($role)) {
                $skipped[] = [
                    'role_id' => $roleId,
                    'reason' => 'User already has role',
                ];
                continue;
            }

            $success = $this->temporalAccessService->grantTemporaryRole(
                $user->id,
                $role->id,
                $durationMinutes,
                $request->reason,
                Auth::id()
            );

            if ($success) {
                $assigned[] = [
                    'role_id' => $roleId,
                    'expires_at' => now()->addMinutes($durationMinutes)->toISOString(),
                ];
            } else {
                $skipped[] = [
                    'role_id' => $roleId,
                    'reason' => 'Service error',
                ];
            }
        }

        return response()->json([
            'message' => 'Temporal access processing completed',
            'assigned' => $assigned,
            'skipped' => $skipped,
        ]);
    }

    public function revokeTemporary(Request $request, User $user, Role $role)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($request, $user, $role) {
                // Find and deactivate the temporary role assignment
                DB::table('role_user')
                    ->where('user_id', $user->id)
                    ->where('role_id', $role->id)
                    ->whereNotNull('expires_at')
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                // Clear user cache
                app(\App\Services\PermissionCacheService::class)->clearUserCache($user->id);

                // Audit the revocation
                PermissionAudit::create([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'action' => 'revoked',
                    'old_values' => [
                        'role_name' => $role->display_name,
                        'user_name' => $user->name,
                        'user_email' => $user->email,
                        'type' => 'temporary',
                    ],
                    'performed_by' => Auth::id(),
                    'reason' => $request->reason,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });

            return response()->json([
                'message' => 'Temporary role revoked successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to revoke temporary role: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function getUserPermissions(User $user)
    {
        $permissions = $user->getAllPermissions()
            ->groupBy('resource')
            ->map(function ($resourcePermissions) {
                return $resourcePermissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'display_name' => $permission->display_name,
                        'action' => $permission->action,
                    ];
                });
            });

        return response()->json([
            'permissions' => $permissions,
            'roles' => $user->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'expires_at' => $role->pivot->expires_at,
                    'is_temporary' => !is_null($role->pivot->expires_at),
                ];
            }),
        ]);
    }

    public function bulkAssign(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array|min:1|max:50',
            'user_ids.*' => 'integer|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'reason' => 'required|string|max:500',
        ]);

        $role = Role::findOrFail($request->role_id);
        $users = User::whereIn('id', $request->user_ids)->get();

        $assigned = 0;
        $skipped = 0;

        try {
            DB::transaction(function () use ($request, $role, $users, &$assigned, &$skipped) {
                foreach ($users as $user) {
                    if ($user->hasRole($role)) {
                        $skipped++;
                        continue;
                    }

                    $user->assignRole($role);
                    $assigned++;

                    // Audit the assignment
                    PermissionAudit::create([
                        'user_id' => $user->id,
                        'role_id' => $role->id,
                        'action' => 'granted',
                        'new_values' => [
                            'role_name' => $role->display_name,
                            'user_name' => $user->name,
                            'user_email' => $user->email,
                            'bulk_operation' => true,
                        ],
                        'performed_by' => Auth::id(),
                        'reason' => $request->reason,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                }
            });

            return response()->json([
                'message' => "Role assigned to {$assigned} users, {$skipped} skipped (already had role)",
                'assigned' => $assigned,
                'skipped' => $skipped,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to perform bulk assignment: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function bulkRevoke(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array|min:1|max:50',
            'user_ids.*' => 'integer|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'reason' => 'required|string|max:500',
        ]);

        $role = Role::findOrFail($request->role_id);
        $users = User::whereIn('id', $request->user_ids)->get();

        // Check for system administrator protection
        if ($role->name === 'system_administrator') {
            $totalAdmins = User::role('system_administrator')->count();
            $revokeCount = $users->filter(fn($user) => $user->hasRole($role))->count();

            if ($totalAdmins - $revokeCount <= 0) {
                return response()->json([
                    'message' => 'Cannot revoke all system administrator roles',
                ], 422);
            }
        }

        $revoked = 0;
        $skipped = 0;

        try {
            DB::transaction(function () use ($request, $role, $users, &$revoked, &$skipped) {
                foreach ($users as $user) {
                    if (!$user->hasRole($role)) {
                        $skipped++;
                        continue;
                    }

                    $user->removeRole($role);
                    $revoked++;

                    // Audit the revocation
                    PermissionAudit::create([
                        'user_id' => $user->id,
                        'role_id' => $role->id,
                        'action' => 'revoked',
                        'old_values' => [
                            'role_name' => $role->display_name,
                            'user_name' => $user->name,
                            'user_email' => $user->email,
                            'bulk_operation' => true,
                        ],
                        'performed_by' => Auth::id(),
                        'reason' => $request->reason,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                }
            });

            return response()->json([
                'message' => "Role revoked from {$revoked} users, {$skipped} skipped (didn't have role)",
                'revoked' => $revoked,
                'skipped' => $skipped,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to perform bulk revocation: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function approveTemporal(Request $request, User $user)
    {
        $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'duration' => 'required|integer|min:1|max:1440',
            'duration_unit' => 'required|in:minutes,hours,days',
            'reason' => 'required|string|min:10|max:500',
            'requested_by' => 'sometimes|integer|exists:users,id',
        ]);

        $durationMinutes = match ($request->duration_unit) {
            'minutes' => (int) $request->duration,
            'hours' => (int) $request->duration * 60,
            'days' => (int) $request->duration * 1440,
            default => 0,
        };

        if ($durationMinutes < 1) {
            return response()->json([
                'message' => 'Invalid duration',
            ], 422);
        }

        $success = $this->temporalAccessService->grantTemporaryRole(
            $user->id,
            (int) $request->role_id,
            $durationMinutes,
            $request->reason,
            Auth::id()
        );

        if ($success) {
            return response()->json([
                'message' => 'Temporal access approved and granted',
            ]);
        }

        return response()->json([
            'message' => 'Failed to approve temporal access',
        ], 422);
    }

    public function denyTemporal(Request $request, User $user, Role $role)
    {
        $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        // Audit the denial for traceability
        PermissionAudit::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'action' => 'denied',
            'old_values' => null,
            'new_values' => null,
            'performed_by' => Auth::id(),
            'reason' => $request->reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return response()->json([
            'message' => 'Temporal access request denied',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\PermissionAudit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status', 'all');

        $roles = Role::query()
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%");
            })
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('is_active', $status === 'active');
            })
            ->withCount([
                'users' => function ($query) {
                    $query->where('role_user.is_active', true);
                },
            ])
            ->with(['permissions:id,name,display_name,resource'])
            ->orderBy('hierarchy_level')
            ->orderBy('display_name')
            ->get();

        $permissions = Permission::where('is_active', true)
            ->orderBy('resource')
            ->orderBy('action')
            ->get();

        $stats = [
            'total_roles' => $roles->count(),
            'active_roles' => $roles->where('is_active', true)->count(),
            'total_permissions' => Permission::where('is_active', true)->count(),
            'total_users_with_roles' => User::whereHas('roles', function ($q) {
                $q->where('role_user.is_active', true);
            })->count(),
        ];

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function show(Role $role)
    {
        $role->load([
            'permissions:id,name,display_name,resource,action',
            'users' => function ($query) {
                $query->where('role_user.is_active', true)
                    ->select('users.id', 'users.name', 'users.email')
                    ->withPivot(['granted_at', 'expires_at', 'granted_by']);
            },
        ]);

        // Get recent permission changes for this role
        $recentAudits = PermissionAudit::where('role_id', $role->id)
            ->with(['user:id,name,email', 'performedBy:id,name,email', 'permission:id,name,display_name'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($audit) {
                return [
                    'id' => $audit->id,
                    'action' => $audit->action,
                    'user_name' => $audit->performedBy?->name ?? 'System',
                    'created_at' => $audit->created_at->toISOString(),
                    'description' => $audit->reason,
                ];
            });

        return Inertia::render('admin/roles/show', [
            'role' => $role,
            'recentAudits' => $recentAudits,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name|regex:/^[a-z_]+$/',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'hierarchy_level' => 'required|integer|min:1|max:10',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role = null;

        try {
            DB::transaction(function () use ($request, &$role) {
                $role = Role::create([
                    'name' => $request->name,
                    'display_name' => $request->display_name,
                    'description' => $request->description,
                    'hierarchy_level' => $request->hierarchy_level,
                    'is_active' => true,
                ]);

                if ($request->has('permissions') && ! empty($request->permissions)) {
                    $permissions = Permission::whereIn('id', $request->permissions)->get();
                    $role->syncPermissions($permissions);

                    // Audit the permission assignments
                    foreach ($permissions as $permission) {
                        PermissionAudit::create([
                            'user_id' => Auth::id(),
                            'permission_id' => $permission->id,
                            'role_id' => $role->id,
                            'action' => 'granted',
                            'new_values' => [
                                'role_name' => $role->display_name,
                                'permission_name' => $permission->display_name,
                            ],
                            'performed_by' => Auth::id(),
                            'reason' => "Role creation: {$role->display_name}",
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                        ]);
                    }
                }
            });

            if (request()->wantsJson()) {
                return response()->json([
                    'message' => 'Role created successfully',
                    'role' => $role,
                ]);
            }

            // For Inertia requests, redirect back so the front-end can refresh state
            return redirect()
                ->back()
                ->with('success', 'Role created successfully');
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create role: '.$e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z_]+$/',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'hierarchy_level' => 'required|integer|min:1|max:10',
            'is_active' => 'boolean',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        try {
            DB::transaction(function () use ($request, $role) {
                $oldValues = [
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'description' => $role->description,
                    'hierarchy_level' => $role->hierarchy_level,
                    'is_active' => $role->is_active,
                ];

                $role->update([
                    'name' => $request->name,
                    'display_name' => $request->display_name,
                    'description' => $request->description,
                    'hierarchy_level' => $request->hierarchy_level,
                    'is_active' => $request->boolean('is_active', true),
                ]);

                $newValues = [
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'description' => $role->description,
                    'hierarchy_level' => $role->hierarchy_level,
                    'is_active' => $role->is_active,
                ];

                // Handle permission updates
                if ($request->has('permissions')) {
                    $newPermissions = Permission::whereIn('id', $request->permissions ?? [])->get();
                    $oldPermissions = $role->permissions;

                    $role->syncPermissions($newPermissions);

                    // Audit permission changes
                    $addedPermissions = $newPermissions->diff($oldPermissions);
                    $removedPermissions = $oldPermissions->diff($newPermissions);

                    foreach ($addedPermissions as $permission) {
                        PermissionAudit::create([
                            'user_id' => Auth::id(),
                            'permission_id' => $permission->id,
                            'role_id' => $role->id,
                            'action' => 'granted',
                            'new_values' => [
                                'role_name' => $role->display_name,
                                'permission_name' => $permission->display_name,
                            ],
                            'performed_by' => Auth::id(),
                            'reason' => "Permission added to role: {$role->display_name}",
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                        ]);
                    }

                    foreach ($removedPermissions as $permission) {
                        PermissionAudit::create([
                            'user_id' => Auth::id(),
                            'permission_id' => $permission->id,
                            'role_id' => $role->id,
                            'action' => 'revoked',
                            'old_values' => [
                                'role_name' => $role->display_name,
                                'permission_name' => $permission->display_name,
                            ],
                            'performed_by' => Auth::id(),
                            'reason' => "Permission removed from role: {$role->display_name}",
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                        ]);
                    }
                }

                // Audit role update
                PermissionAudit::create([
                    'user_id' => Auth::id(),
                    'role_id' => $role->id,
                    'action' => 'modified',
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                    'performed_by' => Auth::id(),
                    'reason' => "Role updated: {$role->display_name}",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });

            if (request()->wantsJson()) {
                return response()->json([
                    'message' => 'Role updated successfully',
                ]);
            }

            return redirect()->back()->with('success', 'Role updated successfully');
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update role: '.$e->getMessage(),
            ], 422);
        }
    }

    public function destroy(Role $role)
    {
        // Prevent deletion of system roles
        if (in_array($role->name, ['system_administrator', 'support_agent'])) {
            return response()->json([
                'message' => 'Cannot delete system roles',
            ], 422);
        }

        // Check if role has users
        if ($role->users()->where('role_user.is_active', true)->exists()) {
            return response()->json([
                'message' => 'Cannot delete role that has assigned users. Please reassign users first.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($role) {
                // Audit the deletion
                PermissionAudit::create([
                    'user_id' => Auth::id(),
                    'role_id' => $role->id,
                    'action' => 'revoked',
                    'old_values' => [
                        'name' => $role->name,
                        'display_name' => $role->display_name,
                        'description' => $role->description,
                    ],
                    'performed_by' => Auth::id(),
                    'reason' => "Role deleted: {$role->display_name}",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                $role->delete();
            });

            if (request()->wantsJson()) {
                return response()->json([
                    'message' => 'Role deleted successfully',
                ]);
            }

            return redirect()->back()->with('success', 'Role deleted successfully');
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete role: '.$e->getMessage(),
            ], 422);
        }
    }

    public function matrix()
    {
        $roles = Role::where('is_active', true)
            ->with('permissions:id,name,display_name,resource')
            ->orderBy('hierarchy_level')
            ->get();

        $permissions = Permission::where('is_active', true)
            ->orderBy('resource')
            ->orderBy('action')
            ->get();

        $matrix = [];
        foreach ($roles as $role) {
            $rolePermissionIds = $role->permissions->pluck('id')->toArray();
            foreach ($permissions as $permission) {
                $matrix[$role->id][$permission->id] = in_array($permission->id, $rolePermissionIds, true);
            }
        }

        return Inertia::render('admin/roles/matrix', [
            'roles' => $roles,
            'permissions' => $permissions,
            'matrix' => $matrix,
        ]);
    }

    public function updateMatrix(Request $request)
    {
        // Support both single update and batched updates
        if ($request->has('changes')) {
            $request->validate([
                'changes' => 'required|array',
                'changes.*.role_id' => 'required|exists:roles,id',
                'changes.*.permission_id' => 'required|exists:permissions,id',
                'changes.*.granted' => 'required|boolean',
            ]);

            $changes = $request->input('changes', []);
        } else {
            $request->validate([
                'role_id' => 'required|exists:roles,id',
                'permission_id' => 'required|exists:permissions,id',
                'granted' => 'required|boolean',
            ]);

            $changes = [
                [
                    'role_id' => $request->role_id,
                    'permission_id' => $request->permission_id,
                    'granted' => $request->granted,
                ],
            ];
        }

        try {
            DB::transaction(function () use ($changes) {
                foreach ($changes as $entry) {
                    /** @var Role $role */
                    $role = Role::findOrFail($entry['role_id']);
                    /** @var Permission $permission */
                    $permission = Permission::findOrFail($entry['permission_id']);

                    if ($entry['granted']) {
                        $role->givePermissionTo($permission);
                        $action = 'granted';
                        $reason = "Permission granted via matrix: {$permission->display_name} to {$role->display_name}";
                    } else {
                        $role->revokePermissionTo($permission);
                        $action = 'revoked';
                        $reason = "Permission revoked via matrix: {$permission->display_name} from {$role->display_name}";
                    }

                    // Audit the change
                    PermissionAudit::create([
                        'user_id' => Auth::id(),
                        'permission_id' => $permission->id,
                        'role_id' => $role->id,
                        'action' => $action,
                        'new_values' => $entry['granted'] ? [
                            'role_name' => $role->display_name,
                            'permission_name' => $permission->display_name,
                        ] : null,
                        'old_values' => ! $entry['granted'] ? [
                            'role_name' => $role->display_name,
                            'permission_name' => $permission->display_name,
                        ] : null,
                        'performed_by' => Auth::id(),
                        'reason' => $reason,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                }
            });

            if (request()->wantsJson()) {
                return response()->json([
                    'message' => 'Permission matrix updated successfully',
                ]);
            }

            // For Inertia requests respond with 204 so front-end stays on page
            return response()->noContent();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update permission matrix: '.$e->getMessage(),
            ], 422);
        }
    }
}

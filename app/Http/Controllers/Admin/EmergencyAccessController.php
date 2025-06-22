<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmergencyAccess;
use App\Models\User;
use App\Models\Permission;
use App\Models\PermissionAudit;
use App\Services\TemporalAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Carbon\Carbon;

class EmergencyAccessController extends Controller
{
    public function index(Request $request)
    {
        $query = EmergencyAccess::with([
            'user:id,name,email',
            'grantedBy:id,name,email'
        ]);

        // Apply filters
        if ($request->filled('user')) {
            $userSearch = $request->user;
            $query->whereHas('user', function ($q) use ($userSearch) {
                $q->where('name', 'like', "%{$userSearch}%")
                    ->orWhere('email', 'like', "%{$userSearch}%");
            });
        }

        if ($request->filled('status')) {
            switch ($request->status) {
                case 'active':
                    $query->active();
                    break;
                case 'expired':
                    $query->expired();
                    break;
                case 'used':
                    $query->whereNotNull('used_at');
                    break;
                case 'unused':
                    $query->unused();
                    break;
            }
        }

        if ($request->filled('date_from')) {
            $query->where('granted_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('granted_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        // Order by most recent first
        $query->orderBy('granted_at', 'desc');

        // Paginate results
        $perPage = $request->get('per_page', 15);
        $emergencyAccesses = $query->paginate($perPage);

        // Transform data for frontend
        $emergencyAccesses->getCollection()->transform(function ($access) {
            return [
                'id' => $access->id,
                'user_id' => $access->user_id,
                'user' => [
                    'id' => $access->user->id,
                    'name' => $access->user->name,
                    'email' => $access->user->email
                ],
                'permissions' => $access->permissions,
                'reason' => $access->reason,
                'granted_at' => $access->granted_at->toISOString(),
                'expires_at' => $access->expires_at->toISOString(),
                'used_at' => $access->used_at?->toISOString(),
                'is_active' => $access->is_active,
                'granted_by' => $access->granted_by,
                'granted_by_user' => [
                    'id' => $access->grantedBy->id,
                    'name' => $access->grantedBy->name,
                    'email' => $access->grantedBy->email
                ],
                'is_valid' => $access->isValid(),
                'remaining_time' => $access->remaining_time,
                'status' => $this->getAccessStatus($access),
            ];
        });

        // Calculate statistics
        $stats = $this->calculateEmergencyStats();

        $filters = $request->only(['user', 'status', 'date_from', 'date_to']);

        return Inertia::render('admin/emergency/index', [
            'emergencyAccesses' => $emergencyAccesses->items(),
            'stats' => $stats,
            'filters' => $filters,
            'pagination' => [
                'current_page' => $emergencyAccesses->currentPage(),
                'last_page' => $emergencyAccesses->lastPage(),
                'per_page' => $emergencyAccesses->perPage(),
                'total' => $emergencyAccesses->total(),
                'from' => $emergencyAccesses->firstItem(),
                'to' => $emergencyAccesses->lastItem(),
            ],
            'available_permissions' => $this->getAvailablePermissions(),
            'users' => User::select('id', 'name', 'email')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', 'exists:permissions,name'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'duration' => ['required', 'integer', 'min:15', 'max:1440'], // 15 minutes to 24 hours
        ]);

        try {
            DB::transaction(function () use ($request) {
                // Create emergency access record
                $emergencyAccess = EmergencyAccess::create([
                    'user_id' => $request->user_id,
                    'permissions' => $request->permissions,
                    'reason' => $request->reason,
                    'expires_at' => now()->addMinutes($request->duration),
                    'granted_by' => Auth::id(),
                ]);

                // Note: Permissions will be validated through the EmergencyAccess model
                // The actual permission granting will be handled by middleware checking
                // for active emergency access records

                // Create audit log
                PermissionAudit::create([
                    'user_id' => $request->user_id,
                    'action' => 'granted',
                    'new_values' => [
                        'emergency_access_id' => $emergencyAccess->id,
                        'permissions' => $request->permissions,
                        'duration_minutes' => $request->duration,
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'performed_by' => Auth::id(),
                    'reason' => "Emergency access granted: {$request->reason}",
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Emergency access granted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to grant emergency access: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $emergencyAccess = EmergencyAccess::with([
            'user:id,name,email',
            'grantedBy:id,name,email'
        ])->findOrFail($id);

        return response()->json([
            'id' => $emergencyAccess->id,
            'user_id' => $emergencyAccess->user_id,
            'user' => [
                'id' => $emergencyAccess->user->id,
                'name' => $emergencyAccess->user->name,
                'email' => $emergencyAccess->user->email
            ],
            'permissions' => $emergencyAccess->permissions,
            'reason' => $emergencyAccess->reason,
            'granted_at' => $emergencyAccess->granted_at->toISOString(),
            'expires_at' => $emergencyAccess->expires_at->toISOString(),
            'used_at' => $emergencyAccess->used_at?->toISOString(),
            'is_active' => $emergencyAccess->is_active,
            'granted_by' => $emergencyAccess->granted_by,
            'granted_by_user' => [
                'id' => $emergencyAccess->grantedBy->id,
                'name' => $emergencyAccess->grantedBy->name,
                'email' => $emergencyAccess->grantedBy->email
            ],
            'is_valid' => $emergencyAccess->isValid(),
            'remaining_time' => $emergencyAccess->remaining_time,
            'status' => $this->getAccessStatus($emergencyAccess),
        ]);
    }

    public function revoke($id, Request $request)
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        try {
            DB::transaction(function () use ($id, $request) {
                $emergencyAccess = EmergencyAccess::findOrFail($id);

                // Deactivate emergency access
                $emergencyAccess->deactivate();

                // Deactivating the EmergencyAccess record is sufficient
                // Middleware will check for active records when validating permissions

                // Create audit log
                PermissionAudit::create([
                    'user_id' => $emergencyAccess->user_id,
                    'action' => 'revoked',
                    'old_values' => [
                        'emergency_access_id' => $emergencyAccess->id,
                        'permissions' => $emergencyAccess->permissions,
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'performed_by' => Auth::id(),
                    'reason' => "Emergency access revoked: {$request->reason}",
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Emergency access revoked successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke emergency access: ' . $e->getMessage()
            ], 500);
        }
    }

    public function markUsed($id, Request $request)
    {
        try {
            $emergencyAccess = EmergencyAccess::findOrFail($id);

            if (!$emergencyAccess->markAsUsed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emergency access is not valid or already expired'
                ], 400);
            }

            // Create audit log
            PermissionAudit::create([
                'user_id' => $emergencyAccess->user_id,
                'action' => 'modified',
                'new_values' => [
                    'emergency_access_id' => $emergencyAccess->id,
                    'used_at' => now()->toISOString(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'performed_by' => Auth::id(),
                'reason' => "Emergency access marked as used",
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Emergency access marked as used'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark emergency access as used: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cleanup(Request $request)
    {
        try {
            // Deactivate expired emergency access records
            $expiredCount = EmergencyAccess::expired()
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // Clean up expired temporal roles
            app(TemporalAccessService::class)->cleanupExpiredPermissions();

            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$expiredCount} expired emergency access records"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup emergency access: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculateEmergencyStats(): array
    {
        $totalAccesses = EmergencyAccess::count();
        $activeAccesses = EmergencyAccess::active()->count();
        $expiredAccesses = EmergencyAccess::expired()->count();
        $usedAccesses = EmergencyAccess::whereNotNull('used_at')->count();

        // Recent emergency access (last 7 days)
        $recentAccesses = EmergencyAccess::where('granted_at', '>=', now()->subDays(7))->count();

        // Most common permissions granted
        $commonPermissions = EmergencyAccess::recent(30)
            ->get()
            ->flatMap(function ($access) {
                return $access->permissions;
            })
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->toArray();

        return [
            'total_accesses' => $totalAccesses,
            'active_accesses' => $activeAccesses,
            'expired_accesses' => $expiredAccesses,
            'used_accesses' => $usedAccesses,
            'recent_accesses' => $recentAccesses,
            'common_permissions' => $commonPermissions,
            'period' => '30 days',
        ];
    }

    private function getAccessStatus(EmergencyAccess $access): string
    {
        if (!$access->is_active) {
            return 'revoked';
        }

        if ($access->expires_at <= now()) {
            return 'expired';
        }

        if ($access->used_at) {
            return 'used';
        }

        return 'active';
    }

    private function getAvailablePermissions(): array
    {
        return Permission::select('name', 'display_name', 'description')
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get()
            ->toArray();
    }
}

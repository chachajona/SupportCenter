<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\TemporalAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class TemporalAccessController extends Controller
{
    public function __construct(
        private readonly TemporalAccessService $temporalAccessService
    ) {}

    /**
     * Display temporal access requests management page
     */
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        // Mock data for now - in production this would come from a temporal_access_requests table
        $requests = collect([
            [
                'id' => 1,
                'user' => [
                    'id' => 2,
                    'name' => 'John Smith',
                    'email' => 'john.smith@company.com',
                    'department_id' => 1,
                ],
                'role' => [
                    'id' => 1,
                    'name' => 'admin',
                    'display_name' => 'System Administrator',
                    'description' => 'Full system access for emergency situations',
                ],
                'duration' => 30,
                'duration_unit' => 'minutes',
                'reason' => 'Emergency system maintenance required due to critical security vulnerability',
                'emergency' => true,
                'status' => 'pending',
                'requested_at' => now()->subMinutes(5)->toISOString(),
                'requested_by' => [
                    'id' => 2,
                    'name' => 'John Smith',
                    'email' => 'john.smith@company.com',
                ],
            ],
            [
                'id' => 2,
                'user' => [
                    'id' => 3,
                    'name' => 'Sarah Johnson',
                    'email' => 'sarah.johnson@company.com',
                    'department_id' => 2,
                ],
                'role' => [
                    'id' => 2,
                    'name' => 'manager',
                    'display_name' => 'Department Manager',
                    'description' => 'Management access for department operations',
                ],
                'duration' => 2,
                'duration_unit' => 'hours',
                'reason' => 'Need to approve time-sensitive department budget allocation',
                'emergency' => false,
                'status' => 'pending',
                'requested_at' => now()->subHours(1)->toISOString(),
                'requested_by' => [
                    'id' => 3,
                    'name' => 'Sarah Johnson',
                    'email' => 'sarah.johnson@company.com',
                ],
            ],
            [
                'id' => 3,
                'user' => [
                    'id' => 4,
                    'name' => 'Mike Davis',
                    'email' => 'mike.davis@company.com',
                    'department_id' => 1,
                ],
                'role' => [
                    'id' => 3,
                    'name' => 'support_lead',
                    'display_name' => 'Support Team Lead',
                    'description' => 'Lead support agent with escalation privileges',
                ],
                'duration' => 4,
                'duration_unit' => 'hours',
                'reason' => 'Covering for team lead during sick leave - need access to escalation tools',
                'emergency' => false,
                'status' => 'approved',
                'requested_at' => now()->subHours(6)->toISOString(),
                'requested_by' => [
                    'id' => 4,
                    'name' => 'Mike Davis',
                    'email' => 'mike.davis@company.com',
                ],
                'reviewed_by' => [
                    'id' => 1,
                    'name' => 'Admin User',
                    'email' => 'admin@company.com',
                ],
                'reviewed_at' => now()->subHours(5)->toISOString(),
                'review_reason' => 'Approved for operational continuity',
            ],
        ]);

        return Inertia::render('admin/temporal/index', [
            'requests' => $requests,
        ]);
    }

    /**
     * Submit a temporal access request
     */
    public function requestAccess(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => 'integer|exists:roles,id',
            'duration' => 'required|integer|min:1|max:1440',
            'duration_unit' => 'required|in:minutes,hours,days',
            'reason' => 'required|string|min:10|max:500',
            'emergency' => 'boolean',
        ]);

        // Convert duration to minutes for validation
        $durationMinutes = match ($validated['duration_unit']) {
            'minutes' => (int) $validated['duration'],
            'hours' => (int) $validated['duration'] * 60,
            'days' => (int) $validated['duration'] * 1440,
            default => 0,
        };

        if ($durationMinutes < 1 || $durationMinutes > 10080) { // Max 7 days
            return response()->json([
                'message' => 'Invalid duration. Maximum allowed is 7 days.',
                'errors' => ['duration' => ['Duration exceeds maximum allowed limit.']],
            ], 422);
        }

        try {
            DB::beginTransaction();

            // In a real implementation, we would create temporal_access_requests records
            // For now, we'll just log this and return success

            foreach ($validated['role_ids'] as $roleId) {
                $role = Role::findOrFail($roleId);

                // Log the request (in production, save to temporal_access_requests table)
                logger()->info('Temporal access request submitted', [
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'duration' => $validated['duration'],
                    'duration_unit' => $validated['duration_unit'],
                    'reason' => $validated['reason'],
                    'emergency' => $validated['emergency'] ?? false,
                    'requested_by' => Auth::id() ? (int) Auth::id() : 0,
                    'requested_at' => now(),
                ]);
            }

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Temporal access request submitted successfully',
                    'data' => [
                        'user_id' => $user->id,
                        'roles_count' => count($validated['role_ids']),
                        'duration' => $validated['duration'].' '.$validated['duration_unit'],
                    ],
                ]);
            }

            return redirect()->back()->with('success', 'Temporal access request submitted for approval');

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Failed to submit temporal access request',
                    'error' => $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->withErrors(['error' => 'Failed to submit request: '.$e->getMessage()]);
        }
    }

    /**
     * Approve a temporal access request
     */
    public function approveRequest(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'duration' => 'required|integer|min:1|max:1440',
            'duration_unit' => 'required|in:minutes,hours,days',
            'reason' => 'required|string|min:10|max:500',
            'requested_by' => 'sometimes|integer|exists:users,id',
        ]);

        $durationMinutes = match ($validated['duration_unit']) {
            'minutes' => (int) $validated['duration'],
            'hours' => (int) $validated['duration'] * 60,
            'days' => (int) $validated['duration'] * 1440,
            default => 0,
        };

        if ($durationMinutes < 1) {
            return response()->json([
                'message' => 'Invalid duration specified.',
                'errors' => ['duration' => ['Duration must be at least 1 minute.']],
            ], 422);
        }

        try {
            $role = Role::findOrFail($validated['role_id']);

            $this->temporalAccessService->grantTemporaryRole(
                $user->id,
                $role->id,
                $durationMinutes,
                $validated['reason'],
                (int) Auth::id()
            );

            // Log the approval (in production, update temporal_access_requests table)
            logger()->info('Temporal access request approved', [
                'user_id' => $user->id,
                'role_id' => $validated['role_id'],
                'duration_minutes' => $durationMinutes,
                'approved_by' => (int) Auth::id(),
                'original_requester' => $validated['requested_by'] ?? null,
                'approved_at' => now(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Temporal access approved and granted',
                    'data' => [
                        'user_id' => $user->id,
                        'role_name' => $role->display_name,
                        'duration_minutes' => $durationMinutes,
                        'expires_at' => now()->addMinutes($durationMinutes),
                    ],
                ]);
            }

            return redirect()->back()->with('success', "Temporal access approved for {$user->name}");

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Failed to approve temporal access',
                    'error' => $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->withErrors(['error' => 'Failed to approve request: '.$e->getMessage()]);
        }
    }

    /**
     * Deny a temporal access request
     */
    public function denyRequest(Request $request, User $user, Role $role): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        try {
            // Log the denial (in production, update temporal_access_requests table)
            logger()->info('Temporal access request denied', [
                'user_id' => $user->id,
                'role_id' => $role->id,
                'denied_by' => Auth::user()->name,
                'denial_reason' => $validated['reason'],
                'denied_at' => now(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Temporal access request denied',
                    'data' => [
                        'user_id' => $user->id,
                        'role_name' => $role->display_name,
                        'denied_by' => Auth::user()->name,
                        'reason' => $validated['reason'],
                    ],
                ]);
            }

            return redirect()->back()->with('success', "Temporal access request denied for {$user->name}");

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Failed to deny temporal access request',
                    'error' => $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->withErrors(['error' => 'Failed to deny request: '.$e->getMessage()]);
        }
    }
}

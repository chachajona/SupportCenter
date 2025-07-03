<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Services\TicketAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * API Controller for ticket management operations.
 */
final class TicketController extends Controller
{
    public function __construct(
        private readonly TicketAssignmentService $assignmentService
    ) {}

    /**
     * Display a listing of tickets.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ticket::class);

        $query = Ticket::with([
            'department:id,name',
            'assignedTo:id,name,email',
            'createdBy:id,name,email',
            'status:id,name,color,is_closed',
            'priority:id,name,color,level',
        ]);

        // Apply user-based scoping
        $user = Auth::user();
        if ($user && ! $user->hasPermissionTo('tickets.view_all')) {
            if ($user->hasPermissionTo('tickets.view_department')) {
                $query->where('department_id', $user->getAttribute('department_id'));
            } elseif ($user->hasPermissionTo('tickets.view_own')) {
                $userId = $user->getKey();
                $query->where(function ($q) use ($userId) {
                    $q->where('assigned_to', $userId)
                        ->orWhere('created_by', $userId);
                });
            }
        }

        // Apply filters
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'LIKE', "%{$search}%")
                    ->orWhere('number', 'LIKE', "%{$search}%");
            });
        }

        if ($department = $request->get('department_id')) {
            $query->where('department_id', $department);
        }

        if ($status = $request->get('status_id')) {
            $query->where('status_id', $status);
        }

        if ($priority = $request->get('priority_id')) {
            $query->where('priority_id', $priority);
        }

        if ($assignedTo = $request->get('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }

        // Sort by latest first
        $query->latest();

        $tickets = $query->paginate($request->get('per_page', 25));

        return TicketResource::collection($tickets);
    }

    /**
     * Store a newly created ticket.
     */
    public function store(Request $request): TicketResource
    {
        $this->authorize('create', Ticket::class);

        $user = Auth::user();
        if (! $user) {
            throw ValidationException::withMessages([
                'auth' => ['User must be authenticated'],
            ]);
        }

        $userDepartmentId = $user->getAttribute('department_id');
        if (! $userDepartmentId) {
            throw ValidationException::withMessages([
                'department' => ['You must be assigned to a department to create tickets.'],
            ]);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority_id' => 'required|exists:ticket_priorities,id',
            'due_at' => 'nullable|date|after:now',
        ]);

        $ticket = Ticket::create([
            ...$validated,
            'department_id' => $userDepartmentId,
            'created_by' => $user->getKey(),
            'status_id' => 1, // Open status
        ]);

        $ticket->load([
            'department:id,name',
            'assignedTo:id,name,email',
            'createdBy:id,name,email',
            'status:id,name,color,is_closed',
            'priority:id,name,color,level',
        ]);

        return new TicketResource($ticket);
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket): TicketResource
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'department:id,name',
            'assignedTo:id,name,email',
            'createdBy:id,name,email',
            'updatedBy:id,name,email',
            'status:id,name,color,is_closed',
            'priority:id,name,color,level',
            'responses.user:id,name,email',
        ]);

        return new TicketResource($ticket);
    }

    /**
     * Update the specified ticket.
     */
    public function update(Request $request, Ticket $ticket): TicketResource
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'subject' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'priority_id' => 'sometimes|required|exists:ticket_priorities,id',
            'status_id' => 'sometimes|required|exists:ticket_statuses,id',
            'due_at' => 'nullable|date|after:now',
        ]);

        $user = Auth::user();
        $validated['updated_by'] = $user?->getKey();

        $ticket->update($validated);

        $ticket->load([
            'department:id,name',
            'assignedTo:id,name,email',
            'createdBy:id,name,email',
            'updatedBy:id,name,email',
            'status:id,name,color,is_closed',
            'priority:id,name,color,level',
        ]);

        return new TicketResource($ticket);
    }

    /**
     * Remove the specified ticket.
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        $this->authorize('delete', $ticket);

        $ticket->delete();

        return response()->json([
            'message' => 'Ticket deleted successfully',
        ]);
    }

    /**
     * Assign a ticket to a user.
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('assign', $ticket);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $assignee = \App\Models\User::findOrFail($validated['user_id']);

        $success = $this->assignmentService->assignTicket(
            $ticket,
            $assignee,
            $validated['reason'] ?? null
        );

        if (! $success) {
            return response()->json([
                'message' => 'Assignment failed. User may not have permission to access this ticket.',
            ], 422);
        }

        return response()->json([
            'message' => 'Ticket assigned successfully',
            'assigned_to' => [
                'id' => $assignee->id,
                'name' => $assignee->name,
                'email' => $assignee->email,
            ],
        ]);
    }

    /**
     * Add a response to a ticket.
     */
    public function addResponse(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $validated = $request->validate([
            'message' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        $user = Auth::user();
        $response = TicketResponse::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user?->getKey(),
            'message' => $validated['message'],
            'is_internal' => $validated['is_internal'] ?? false,
            'is_email' => false,
        ]);

        $response->load('user:id,name,email');

        return response()->json([
            'message' => 'Response added successfully',
            'response' => [
                'id' => $response->id,
                'message' => $response->message,
                'is_internal' => $response->is_internal,
                'user' => [
                    'id' => $response->user->id,
                    'name' => $response->user->name,
                    'email' => $response->user->email,
                ],
                'created_at' => $response->created_at->toISOString(),
            ],
        ]);
    }
}

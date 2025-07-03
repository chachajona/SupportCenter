<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketResponse;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for handling ticket management operations.
 */
final class TicketController extends Controller
{
    /**
     * Display a listing of tickets.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Ticket::class);

        // Build query with eager loading and proper scoping
        $query = Ticket::with([
            'department:id,name',
            'assignedTo:id,name,email',
            'createdBy:id,name,email',
            'status:id,name,color,is_closed',
            'priority:id,name,color,level',
        ])
            ->select([
                'id',
                'number',
                'subject',
                'priority_id',
                'status_id',
                'department_id',
                'assigned_to',
                'created_by',
                'due_at',
                'created_at',
                'updated_at',
            ]);

        // Apply user-based scoping based on permissions
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
        $search = $request->input('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'LIKE', "%{$search}%")
                    ->orWhere('number', 'LIKE', "%{$search}%");
            });
        }

        if ($department = $request->input('department')) {
            $query->where('department_id', $department);
        }

        if ($status = $request->input('status')) {
            $query->where('status_id', $status);
        }

        if ($assignedTo = $request->input('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }

        if ($priority = $request->input('priority')) {
            $query->where('priority_id', $priority);
        }

        $tickets = $query->latest()->paginate(25);

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'filters' => $request->only(['search', 'department', 'status', 'assigned_to', 'priority']),
            'departments' => Department::select('id', 'name')->get(),
            'statuses' => TicketStatus::select('id', 'name', 'color')->get(),
            'priorities' => TicketPriority::select('id', 'name', 'color', 'level')->get(),
            'users' => User::select('id', 'name')->get(),
        ]);
    }

    /**
     * Show the form for creating a new ticket.
     */
    public function create(): Response
    {
        $this->authorize('create', Ticket::class);

        return Inertia::render('Tickets/Create', [
            'statuses' => TicketStatus::select('id', 'name', 'color')->get(),
            'priorities' => TicketPriority::select('id', 'name', 'color', 'level')->get(),
        ]);
    }

    /**
     * Store a newly created ticket.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Ticket::class);

        $user = Auth::user();
        if (! $user) {
            abort(401, 'User must be authenticated');
        }

        $userDepartmentId = $user->getAttribute('department_id');
        if (! $userDepartmentId) {
            return redirect()->back()->withErrors(['department' => 'You must be assigned to a department to create tickets.']);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority_id' => 'required|exists:ticket_priorities,id',
        ]);

        $ticket = Ticket::create([
            ...$validated,
            'department_id' => $userDepartmentId,
            'created_by' => $user->getKey(),
            'status_id' => 1, // Open status
        ]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket created successfully.');
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket): Response
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

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
            'departments' => Department::select('id', 'name')->get(),
            'priorities' => TicketPriority::select('id', 'name', 'color', 'level')->get(),
            'statuses' => TicketStatus::select('id', 'name', 'color', 'is_closed')->get(),
        ]);
    }

    /**
     * Show the form for editing the ticket.
     */
    public function edit(Ticket $ticket): Response
    {
        $this->authorize('update', $ticket);

        $ticket->load(['department', 'status', 'priority']);

        return Inertia::render('Tickets/Edit', [
            'ticket' => $ticket,
        ]);
    }

    /**
     * Update the specified ticket.
     */
    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'subject' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'priority_id' => 'sometimes|required|exists:ticket_priorities,id',
            'status_id' => 'sometimes|required|exists:ticket_statuses,id',
            'due_at' => 'sometimes|nullable|date|after:now',
        ]);

        // Check if status is being changed to closed and set resolved_at
        if (isset($validated['status_id'])) {
            $newStatus = TicketStatus::find($validated['status_id']);
            if ($newStatus && $newStatus->is_closed && ! $ticket->resolved_at) {
                $validated['resolved_at'] = now();
            }
        }

        $user = Auth::user();
        $validated['updated_by'] = $user?->getKey();

        $ticket->update($validated);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket updated successfully.');
    }

    /**
     * Remove the specified ticket.
     */
    public function destroy(Ticket $ticket): RedirectResponse
    {
        $this->authorize('delete', $ticket);

        $ticket->delete();

        return redirect()->route('tickets.index')
            ->with('success', 'Ticket deleted successfully.');
    }

    /**
     * Assign a ticket to a user.
     */
    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('assign', $ticket);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string|max:255',
        ]);

        $assignee = User::findOrFail($validated['user_id']);
        $reason = $validated['reason'] ?? null;

        $assignmentService = app(TicketAssignmentService::class);
        $success = $assignmentService->assignTicket($ticket, $assignee, $reason);

        if ($success) {
            return redirect()->route('tickets.show', $ticket)
                ->with('success', 'Ticket assigned successfully.');
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('error', 'Failed to assign ticket. User may not have appropriate permissions.');
    }

    /**
     * Transfer a ticket to another user.
     */
    public function transfer(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('transfer', $ticket);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string|max:255',
        ]);

        $newAssignee = User::findOrFail($validated['user_id']);
        $reason = $validated['reason'] ?? null;

        $assignmentService = app(TicketAssignmentService::class);
        $success = $assignmentService->transferTicket($ticket, $newAssignee, $reason);

        if ($success) {
            return redirect()->route('tickets.show', $ticket)
                ->with('success', 'Ticket transferred successfully.');
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('error', 'Failed to transfer ticket. User may not have appropriate permissions.');
    }

    /**
     * Add a response to the ticket.
     */
    public function addResponse(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'message' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        $user = Auth::user();

        TicketResponse::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $user?->getKey(),
            'message' => $validated['message'],
            'is_internal' => $validated['is_internal'] ?? false,
            'is_email' => false,
        ]);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Response added successfully.');
    }
}

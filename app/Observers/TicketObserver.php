<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\PermissionAudit;
use App\Models\Ticket;
use App\Services\SlackNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Observer for Ticket model events.
 */
final class TicketObserver
{
    public function __construct(
        private readonly SlackNotificationService $slackService
    ) {}

    /**
     * Handle the Ticket "created" event.
     */
    public function created(Ticket $ticket): void
    {
        // Safely capture request information (may be null in CLI/queue contexts)
        $ipAddress = app()->runningInConsole() ? null : request()->ip();
        $userAgent = app()->runningInConsole() ? null : request()->userAgent();

        // Create audit record with graceful error handling so ticket creation is never blocked
        try {
            PermissionAudit::create([
                'user_id' => $ticket->created_by,
                'action' => 'ticket_created',
                'old_values' => null,
                'new_values' => [
                    'ticket_id' => $ticket->getKey(),
                    'subject' => $ticket->subject,
                    'department_id' => $ticket->department_id,
                    'priority_id' => $ticket->priority_id,
                    'status_id' => $ticket->status_id,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'performed_by' => Auth::id(),
                'reason' => 'Ticket created',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Log the exception but do not interrupt the ticket creation flow
            Log::error('Failed to create PermissionAudit for ticket creation', [
                'ticket_id' => $ticket->getKey(),
                'exception' => $e->getMessage(),
            ]);
        }

        // Load required relationships for notifications
        $ticket->load(['department', 'priority', 'status', 'createdBy']);

        // Send new ticket notification
        $this->slackService->notifyNewTicket($ticket);

        // Send high priority alert if needed
        if ($ticket->priority->level >= 3) {
            $this->slackService->notifyHighPriorityTicket($ticket);
        }
    }

    /**
     * Handle the Ticket "updated" event.
     */
    public function updated(Ticket $ticket): void
    {
        // Create audit record for updates if there are any changes
        $changes = $ticket->getChanges();
        if (! empty($changes)) {
            $ipAddress = app()->runningInConsole() ? null : request()->ip();
            $userAgent = app()->runningInConsole() ? null : request()->userAgent();

            try {
                PermissionAudit::create([
                    'user_id' => $ticket->updated_by ?? Auth::id(),
                    'action' => 'ticket_updated',
                    'old_values' => array_intersect_key($ticket->getOriginal(), $changes),
                    'new_values' => array_merge($changes, ['ticket_id' => $ticket->getKey()]),
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'performed_by' => Auth::id(),
                    'reason' => 'Ticket updated',
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to create PermissionAudit for ticket update', [
                    'ticket_id' => $ticket->getKey(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        // Check if ticket was resolved
        if ($ticket->isDirty('resolved_at') && $ticket->resolved_at !== null) {
            $ticket->load(['department', 'priority', 'status', 'assignedTo']);
            $this->slackService->notifyTicketResolution($ticket);
        }

        // Check if priority was upgraded to high/critical
        if ($ticket->isDirty('priority_id')) {
            $ticket->load(['priority', 'department', 'createdBy']);
            if ($ticket->priority->level >= 3) {
                $this->slackService->notifyHighPriorityTicket($ticket);
            }
        }
    }
}

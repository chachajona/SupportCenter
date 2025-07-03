<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Ticket;
use App\Services\SlackNotificationService;

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

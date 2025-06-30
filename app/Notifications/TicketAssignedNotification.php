<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a ticket is assigned to a user.
 */
final class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Ticket $ticket)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param object $notifiable
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $departmentName = $this->ticket->department->getAttribute('name') ?? 'Unknown Department';
        $priorityName = $this->ticket->priority->getAttribute('name') ?? 'Unknown Priority';

        return (new MailMessage)
            ->subject("Ticket #{$this->ticket->number} Assigned to You")
            ->line("You have been assigned a new ticket:")
            ->line("**Subject:** {$this->ticket->subject}")
            ->line("**Priority:** {$priorityName}")
            ->line("**Department:** {$departmentName}")
            ->action('View Ticket', url("/tickets/{$this->ticket->getKey()}"))
            ->line('Please review and respond to this ticket promptly.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param object $notifiable
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $departmentName = $this->ticket->department->getAttribute('name') ?? 'Unknown Department';
        $priorityName = $this->ticket->priority->getAttribute('name') ?? 'Unknown Priority';

        return [
            'ticket_id' => $this->ticket->getKey(),
            'ticket_number' => $this->ticket->number,
            'subject' => $this->ticket->subject,
            'priority' => $priorityName,
            'department' => $departmentName,
            'message' => "You have been assigned ticket #{$this->ticket->number}: {$this->ticket->subject}.",
            'action_url' => url("/tickets/{$this->ticket->getKey()}")
        ];
    }
}

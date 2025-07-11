<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class FollowUpReminderNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Ticket $ticket) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Follow Up Reminder')
            ->line('This is an automated reminder regarding ticket #'.$this->ticket->number)
            ->action('View Ticket', url('/tickets/'.$this->ticket->id));
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmergencyAccessAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $token,
        private string $reason
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = url("/emergency-access/{$this->token}");

        return (new MailMessage)
            ->subject('Emergency Access Request')
            ->greeting("Hello {$notifiable->name},")
            ->line('An emergency access request has been submitted for your account.')
            ->line("Reason: {$this->reason}")
            ->line('If this was you, click the button below to access your account:')
            ->action('Emergency Access', $url)
            ->line('This link will expire in 24 hours.')
            ->line('If you did not request emergency access, please ignore this email and contact support immediately.')
            ->salutation('Best regards, The Security Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'token' => $this->token,
            'reason' => $this->reason,
            'expires_at' => now()->addHours(24),
        ];
    }
}

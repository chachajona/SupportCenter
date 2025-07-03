<?php

declare(strict_types=1);

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class SuspiciousActivityAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private array $activityData
    ) {}

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
     * Safely convert timestamp to Carbon instance.
     *
     * Handles various timestamp formats and ensures we always have a valid Carbon instance.
     */
    private function safeTimestamp(): Carbon
    {
        $timestamp = $this->activityData['timestamp'] ?? null;

        // If timestamp is already a Carbon/DateTime instance, return it as Carbon
        if ($timestamp instanceof \DateTimeInterface) {
            return Carbon::parse($timestamp);
        }

        // If timestamp is a string, try to parse it
        if (is_string($timestamp) && ! empty($timestamp)) {
            try {
                return Carbon::parse($timestamp);
            } catch (\Exception $e) {
                // Log the error for debugging but don't fail the notification
                logger()->warning('Invalid timestamp format in SuspiciousActivityAlert', [
                    'timestamp' => $timestamp,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback to current time
        return now();
    }

    /**
     * Format alerts as HTML unordered list for proper email rendering.
     */
    private function formatAlertsAsHtml(array $alerts): string
    {
        if (empty($alerts)) {
            return '';
        }

        $listItems = array_map(fn ($alert) => '<li>'.htmlspecialchars($alert, ENT_QUOTES, 'UTF-8').'</li>', $alerts);

        return '<ul>'.implode('', $listItems).'</ul>';
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $alerts = $this->formatAlertsAsHtml($this->activityData['alerts'] ?? []);

        return (new MailMessage)
            ->subject('🚨 Suspicious Activity Detected')
            ->greeting("Hello {$notifiable->name},")
            ->line('We detected suspicious activity on your account.')
            ->line('**IP Address:** '.($this->activityData['ip_address'] ?? 'Unknown'))
            ->line('**Time:** '.$this->safeTimestamp()->format('Y-m-d H:i:s T'))
            ->line('**Security Score:** '.($this->activityData['score'] ?? 0).'/100')
            ->when(! empty($alerts), function (MailMessage $mail) use ($alerts) {
                return $mail
                    ->line('Security Alerts:')
                    ->line(new HtmlString($alerts));
            })
            ->line('If this was you, you can safely ignore this email. If you did not authorize this activity, please:')
            ->line('1. Change your password immediately')
            ->line('2. Review your account security settings')
            ->line('3. Contact support if you need assistance')
            ->action('Review Security Settings', url('/settings/security'))
            ->line('Thank you for helping us keep your account secure!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ip_address' => $this->activityData['ip_address'] ?? 'Unknown',
            'alerts' => $this->activityData['alerts'] ?? [],
            'score' => $this->activityData['score'] ?? 0,
            'timestamp' => $this->safeTimestamp(),
        ];
    }
}

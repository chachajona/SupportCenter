<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\EmergencyAccess;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmergencyRecoveryCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private EmergencyAccess $emergencyAccess,
        private array $incidentReport,
        private User $performer
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = $this->emergencyAccess->user;
        $riskLevel = $this->incidentReport['usage_analysis']['risk_level'];
        $wasUsed = $this->incidentReport['usage_analysis']['was_actually_used'];

        return (new MailMessage)
            ->subject('✅ Emergency Access Recovery Completed')
            ->greeting("Recovery Complete - {$notifiable->name}")
            ->line("Emergency access recovery has been completed for **{$user->name}** ({$user->email})")
            ->line("**Performed by:** {$this->performer->name}")
            ->line('**Risk Level:** '.ucfirst($riskLevel))
            ->line('**Access was used:** '.($wasUsed ? 'Yes' : 'No'))
            ->line('**Recovery completed at:** '.now()->format('Y-m-d H:i:s T'))
            ->action('View Incident Report', url("/admin/emergency/{$this->emergencyAccess->id}"))
            ->line('Post-incident cleanup has been completed successfully.')
            ->line('Review the incident report for detailed analysis and follow-up actions.')
            ->salutation('Security Team - Support Center');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Emergency Access Recovery Completed',
            'message' => "Recovery completed for {$this->emergencyAccess->user->name}",
            'type' => 'emergency_recovery',
            'emergency_access_id' => $this->emergencyAccess->id,
            'incident_report_id' => $this->incidentReport['id'],
            'performed_by' => $this->performer->id,
            'risk_level' => $this->incidentReport['usage_analysis']['risk_level'],
            'priority' => 'medium',
        ];
    }
}

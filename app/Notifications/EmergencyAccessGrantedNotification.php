<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\SecurityEventType;
use App\Events\SecurityEvent;
use App\Models\EmergencyAccess;
use App\Models\SecurityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmergencyAccessGrantedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private EmergencyAccess $emergencyAccess
    ) {
        // Fire real-time WebSocket event
        $securityLog = SecurityLog::create([
            'user_id' => $this->emergencyAccess->user_id,
            'event_type' => SecurityEventType::EMERGENCY_ACCESS,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'details' => [
                'type' => 'emergency_access_granted',
                'emergency_access_id' => $this->emergencyAccess->id,
                'permissions' => $this->emergencyAccess->permissions,
                'reason' => $this->emergencyAccess->reason,
                'granted_by' => $this->emergencyAccess->granted_by,
                'expires_at' => $this->emergencyAccess->expires_at,
            ],
        ]);

        // Broadcast real-time security event
        broadcast(new SecurityEvent($securityLog))->toOthers();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = $this->emergencyAccess->user;
        $grantedBy = $this->emergencyAccess->grantedBy;
        $permissions = implode(', ', $this->emergencyAccess->permissions);

        return (new MailMessage)
            ->subject('🚨 EMERGENCY ACCESS GRANTED - Immediate Action Required')
            ->greeting("SECURITY ALERT - {$notifiable->name}")
            ->line("Emergency access has been granted to **{$user->name}** ({$user->email})")
            ->line("**Granted by:** {$grantedBy->name}")
            ->line("**Reason:** {$this->emergencyAccess->reason}")
            ->line("**Permissions:** {$permissions}")
            ->line("**Expires:** {$this->emergencyAccess->expires_at->format('Y-m-d H:i:s T')}")
            ->line("**Remaining Time:** {$this->emergencyAccess->remaining_time}")
            ->action('Review Emergency Access', url("/admin/emergency/{$this->emergencyAccess->id}"))
            ->line('⚠️ **This is a high-priority security event. Please review immediately.**')
            ->line('If this access was not authorized, revoke it immediately and investigate.')
            ->salutation('Security Team - Support Center');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Emergency Access Granted',
            'message' => "Emergency access granted to {$this->emergencyAccess->user->name}",
            'type' => 'emergency_access',
            'emergency_access_id' => $this->emergencyAccess->id,
            'user_id' => $this->emergencyAccess->user_id,
            'granted_by' => $this->emergencyAccess->granted_by,
            'permissions' => $this->emergencyAccess->permissions,
            'expires_at' => $this->emergencyAccess->expires_at,
            'priority' => 'high',
        ];
    }
}

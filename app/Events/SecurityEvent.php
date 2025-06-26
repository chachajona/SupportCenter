<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\SecurityLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

final class SecurityEvent implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public SecurityLog $log)
    {
        // The SecurityLog model is already serializable
    }

    public function broadcastOn(): Channel
    {
        return new Channel('security-events');
    }

    public function broadcastAs(): string
    {
        return 'security.event';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->log->id,
            'event_type' => $this->log->event_type->value,
            'ip_address' => $this->log->ip_address,
            'user_agent' => $this->log->user_agent,
            'created_at' => $this->log->created_at?->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SecurityLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SuspiciousActivityAlert;

final class ThreatResponseService
{
    /** Block offending IP for 30 minutes and notify user */
    public function handle(SecurityLog $log): void
    {
        if (!$log->event_type->isThreat()) {
            return;
        }

        // Basic auto block: store in cache so IpAllowlistMiddleware can consult if implemented
        if ($log->ip_address) {
            Cache::put("blocked_ip:{$log->ip_address}", true, now()->addMinutes(30));
        }

        // Notify affected user (if any)
        if ($log->user) {
            Notification::send($log->user, new SuspiciousActivityAlert([
                'ip' => $log->ip_address,
                'userAgent' => $log->user_agent,
                'event' => $log->event_type->value,
            ]));
        }
    }
}

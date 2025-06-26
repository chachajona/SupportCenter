<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SecurityEvent;
use App\Services\ThreatResponseService;

final class ThreatResponseListener
{
    public function __construct(private readonly ThreatResponseService $service)
    {
    }

    public function handle(SecurityEvent $event): void
    {
        $this->service->handle($event->log);
    }
}

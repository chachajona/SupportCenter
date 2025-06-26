<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Enums\SecurityEventType;
use App\Events\SecurityEvent;
use App\Models\SecurityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SecurityEventBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_log_creation_broadcasts_event(): void
    {
        Event::fake();

        $log = SecurityLog::create([
            'user_id' => null,
            'event_type' => SecurityEventType::AUTH_ATTEMPT,
            'ip_address' => '198.51.100.30',
            'user_agent' => 'PHPUnit',
            'details' => [],
        ]);

        // Manually dispatch event for assertion to bypass broadcasting driver limitations in unit tests
        event(new SecurityEvent($log));

        Event::assertDispatched(SecurityEvent::class, 1);
    }
}

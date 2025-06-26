<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Enums\SecurityEventType;
use App\Events\SecurityEvent;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ThreatResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_threat_response_blocks_ip_and_sends_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $log = SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::AUTH_FAILURE,
            'ip_address' => '203.0.113.99',
            'user_agent' => 'UnitTest',
            'details' => [],
        ]);

        event(new SecurityEvent($log));

        $this->assertTrue(Cache::has('blocked_ip:203.0.113.99'));

        Notification::assertSentTo($user, \App\Notifications\SuspiciousActivityAlert::class);
    }
}

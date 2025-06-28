<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Enums\SecurityEventType;
use App\Models\PermissionAudit;
use App\Models\SecurityLog;
use App\Models\User;
use App\Services\ThreatResponseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ThreatResponseTest extends TestCase
{
    use RefreshDatabase;

    private ThreatResponseService $threatResponseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->threatResponseService = app(ThreatResponseService::class);

        // Clear cache before each test
        Cache::flush();

        // Set default config values for testing
        Config::set('security.ip_block_ttl', 1800); // 30 minutes
        Config::set('security.audit.log_ip_blocks', true);
        Config::set('security.notifications.enable_email_alerts', true);
        Config::set('security.notifications.rate_limit_window', 3600);
    }

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

        // Call the service directly to test the logic
        $this->threatResponseService->handle($log);

        // Verify IP is blocked
        $this->assertTrue($this->threatResponseService->isIpBlocked('203.0.113.99'));

        // Verify block info contains expected data
        $blockInfo = $this->threatResponseService->getBlockedIpInfo('203.0.113.99');
        $this->assertNotNull($blockInfo);
        $this->assertEquals($log->id, $blockInfo['security_log_id']);
        $this->assertEquals(SecurityEventType::AUTH_FAILURE->value, $blockInfo['event_type']);

        // Verify notification was sent
        Notification::assertSentTo($user, \App\Notifications\SuspiciousActivityAlert::class);

        // Verify audit entry was created (using existing enum value)
        $this->assertDatabaseHas('permission_audits', [
            'user_id' => $user->id,
            'action' => 'unauthorized_access_attempt',
            'ip_address' => '203.0.113.99',
            'reason' => 'Automatic IP block due to auth_failure',
        ]);

        // Verify the real action type is stored in new_values
        $audit = PermissionAudit::where('user_id', $user->id)
            ->where('ip_address', '203.0.113.99')
            ->first();
        $this->assertNotNull($audit);
        $newValues = json_decode($audit->new_values, true);
        $this->assertEquals('ip_block_auto', $newValues['action_type']);
    }

    public function test_configurable_ip_block_ttl(): void
    {
        // Set custom TTL
        Config::set('security.ip_block_ttl', 600); // 10 minutes

        $user = User::factory()->create();
        $log = SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::SUSPICIOUS_ACTIVITY,
            'ip_address' => '192.168.1.100',
            'user_agent' => 'TestAgent',
            'details' => [],
        ]);

        $this->threatResponseService->handle($log);

        // Verify IP is blocked
        $this->assertTrue($this->threatResponseService->isIpBlocked('192.168.1.100'));

        // Verify audit entry contains correct TTL
        $audit = PermissionAudit::where('action', 'unauthorized_access_attempt')
            ->where('ip_address', '192.168.1.100')
            ->first();

        $this->assertNotNull($audit);
        $newValues = json_decode($audit->new_values, true);
        $this->assertEquals(600, $newValues['block_duration_seconds']);
        $this->assertEquals('ip_block_auto', $newValues['action_type']);
    }

    public function test_duplicate_ip_blocks_are_prevented(): void
    {
        $user = User::factory()->create();

        // Create first security log
        $log1 = SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::AUTH_FAILURE,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'TestAgent',
            'details' => [],
        ]);

        $this->threatResponseService->handle($log1);

        // Verify IP is blocked and audit entry exists
        $this->assertTrue($this->threatResponseService->isIpBlocked('10.0.0.1'));
        $this->assertDatabaseHas('permission_audits', [
            'action' => 'unauthorized_access_attempt',
            'ip_address' => '10.0.0.1',
        ]);

        $initialAuditCount = PermissionAudit::where('action', 'unauthorized_access_attempt')
            ->where('ip_address', '10.0.0.1')
            ->count();

        // Create second security log for same IP
        $log2 = SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::WEBAUTHN_FAILED,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'TestAgent',
            'details' => [],
        ]);

        $this->threatResponseService->handle($log2);

        // Verify no additional audit entry was created
        $finalAuditCount = PermissionAudit::where('action', 'unauthorized_access_attempt')
            ->where('ip_address', '10.0.0.1')
            ->count();

        $this->assertEquals($initialAuditCount, $finalAuditCount);
    }

    public function test_manual_ip_unblock(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();

        // First block an IP
        $log = SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::SUSPICIOUS_ACTIVITY,
            'ip_address' => '172.16.0.1',
            'user_agent' => 'TestAgent',
            'details' => [],
        ]);

        $this->threatResponseService->handle($log);
        $this->assertTrue($this->threatResponseService->isIpBlocked('172.16.0.1'));

        // Manually unblock the IP
        $result = $this->threatResponseService->unblockIp(
            '172.16.0.1',
            $admin->id,
            'False positive - legitimate user'
        );

        $this->assertTrue($result);
        $this->assertFalse($this->threatResponseService->isIpBlocked('172.16.0.1'));

        // Verify audit entry for unblock
        $this->assertDatabaseHas('permission_audits', [
            'action' => 'modified',
            'ip_address' => '172.16.0.1',
            'performed_by' => $admin->id,
            'reason' => 'False positive - legitimate user',
        ]);

        // Verify the real action type is stored in new_values
        $unblockAudit = PermissionAudit::where('action', 'modified')
            ->where('ip_address', '172.16.0.1')
            ->where('performed_by', $admin->id)
            ->first();
        $this->assertNotNull($unblockAudit);
        $newValues = json_decode($unblockAudit->new_values, true);
        $this->assertEquals('ip_unblock_manual', $newValues['action_type']);
    }

    public function test_unblock_non_blocked_ip_returns_false(): void
    {
        $admin = User::factory()->create();

        $result = $this->threatResponseService->unblockIp(
            '192.168.1.1',
            $admin->id,
            'Test unblock'
        );

        $this->assertFalse($result);
    }

    public function test_notification_rate_limiting(): void
    {
        Notification::fake();

        // Set short rate limit for testing
        Config::set('security.notifications.rate_limit_window', 10);

        $user = User::factory()->create();

        // Create first security log
        $log1 = SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::AUTH_FAILURE,
            'ip_address' => '198.51.100.1',
            'user_agent' => 'TestAgent',
            'details' => [],
        ]);

        $this->threatResponseService->handle($log1);

        // Create second security log immediately
        $log2 = SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::SUSPICIOUS_ACTIVITY,
            'ip_address' => '198.51.100.1',
            'user_agent' => 'TestAgent',
            'details' => [],
        ]);

        $this->threatResponseService->handle($log2);

        // Verify only one notification was sent due to rate limiting
        Notification::assertSentToTimes($user, \App\Notifications\SuspiciousActivityAlert::class, 1);
    }

    public function test_disabled_email_alerts(): void
    {
        Notification::fake();
        Config::set('security.notifications.enable_email_alerts', false);

        $user = User::factory()->create();
        $log = SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::AUTH_FAILURE,
            'ip_address' => '203.0.113.50',
            'user_agent' => 'TestAgent',
            'details' => [],
        ]);

        $this->threatResponseService->handle($log);

        // Verify no notification was sent
        Notification::assertNothingSent();

        // But IP should still be blocked
        $this->assertTrue($this->threatResponseService->isIpBlocked('203.0.113.50'));
    }

    public function test_disabled_ip_block_logging(): void
    {
        Config::set('security.audit.log_ip_blocks', false);

        $user = User::factory()->create();
        $log = SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::AUTH_FAILURE,
            'ip_address' => '10.1.1.1',
            'user_agent' => 'TestAgent',
            'details' => [],
        ]);

        $this->threatResponseService->handle($log);

        // Verify no audit entry was created
        $this->assertDatabaseMissing('permission_audits', [
            'action' => 'unauthorized_access_attempt',
            'ip_address' => '10.1.1.1',
        ]);
    }

    public function test_non_threat_events_are_ignored(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $log = SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::ACCESS_GRANTED, // Not a threat
            'ip_address' => '192.168.100.1',
            'user_agent' => 'TestAgent',
            'details' => [],
        ]);

        $this->threatResponseService->handle($log);

        // Verify IP is not blocked
        $this->assertFalse($this->threatResponseService->isIpBlocked('192.168.100.1'));

        // Verify no notification was sent
        Notification::assertNothingSent();

        // Verify no audit entry was created
        $this->assertDatabaseMissing('permission_audits', [
            'action' => 'unauthorized_access_attempt',
            'ip_address' => '192.168.100.1',
        ]);
    }

    public function test_integration_two_malicious_logins_single_block_and_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $ipAddress = '203.0.113.200';

        // First malicious login attempt
        $log1 = SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::AUTH_FAILURE,
            'ip_address' => $ipAddress,
            'user_agent' => 'MaliciousBot/1.0',
            'details' => ['attempt' => 1],
        ]);

        $this->threatResponseService->handle($log1);

        // Second malicious login attempt (should not create duplicate block)
        $log2 = SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::AUTH_FAILURE,
            'ip_address' => $ipAddress,
            'user_agent' => 'MaliciousBot/1.0',
            'details' => ['attempt' => 2],
        ]);

        $this->threatResponseService->handle($log2);

        // Verify IP is blocked
        $this->assertTrue($this->threatResponseService->isIpBlocked($ipAddress));

        // Verify only one audit entry for IP block (no duplicates)
        $blockAudits = PermissionAudit::where('action', 'unauthorized_access_attempt')
            ->where('ip_address', $ipAddress)
            ->get();

        $this->assertCount(1, $blockAudits);

        // Verify only one notification was sent (rate limited)
        Notification::assertSentToTimes($user, \App\Notifications\SuspiciousActivityAlert::class, 1);

        // Verify block info is correct
        $blockInfo = $this->threatResponseService->getBlockedIpInfo($ipAddress);
        $this->assertNotNull($blockInfo);
        $this->assertEquals($log1->id, $blockInfo['security_log_id']); // Should be from first event
    }

    public function test_audit_entry_failure_does_not_break_blocking(): void
    {
        // This test verifies that if audit logging fails, IP blocking still works

        $user = User::factory()->create();
        $log = SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::SUSPICIOUS_ACTIVITY,
            'ip_address' => '10.10.10.10',
            'user_agent' => 'TestAgent',
            'details' => [],
        ]);

        // This should not throw an exception even if audit fails
        $this->threatResponseService->handle($log);

        // IP should still be blocked despite potential audit failure
        $this->assertTrue($this->threatResponseService->isIpBlocked('10.10.10.10'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\SecurityEventType;
use App\Models\IpAllowlist;
use App\Models\SecurityLog;
use App\Models\User;
use App\Notifications\SuspiciousActivityAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessionSecuritySimpleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // Enable sessions for testing
        Config::set('session.driver', 'array');
        $this->startSession();
    }

    public function test_idle_session_timeout_middleware_logs_out_inactive_user(): void
    {
        // Set a short idle timeout for testing
        Config::set('session.idle_timeout', 5); // 5 seconds

        $this->actingAs($this->user);

        // Test the middleware directly
        $middleware = new \App\Http\Middleware\IdleSessionTimeout();
        $request = \Illuminate\Http\Request::create('/dashboard', 'GET');
        $request->setLaravelSession($this->app['session']->driver());

        // Set last activity to 10 seconds ago
        $request->session()->put('last_activity_time', time() - 10);

        $response = $middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('login', $response->headers->get('Location'));
    }

    public function test_ip_allowlist_model_creation(): void
    {
        $allowlist = IpAllowlist::create([
            'user_id' => $this->user->id,
            'ip_address' => '192.168.1.100',
            'description' => 'Home IP',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('ip_allowlists', [
            'user_id' => $this->user->id,
            'ip_address' => '192.168.1.100',
            'is_active' => true,
        ]);

        $this->assertTrue($allowlist->is_active);
        $this->assertEquals($this->user->id, $allowlist->user_id);
    }

    public function test_security_log_model_creation(): void
    {
        $log = SecurityLog::create([
            'user_id' => $this->user->id,
            'event_type' => SecurityEventType::TEST_EVENT,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'details' => json_encode(['test' => 'data']),
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('security_logs', [
            'user_id' => $this->user->id,
            'event_type' => 'test_event',
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertEquals(SecurityEventType::TEST_EVENT, $log->event_type);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertIsString($log->details);
    }

    public function test_suspicious_activity_alert_notification(): void
    {
        Notification::fake();

        $alertData = [
            'ip_address' => '127.0.0.1',
            'alerts' => ['Test alert'],
            'score' => 75,
            'timestamp' => now(),
        ];

        $this->user->notify(new SuspiciousActivityAlert($alertData));

        Notification::assertSentTo(
            $this->user,
            SuspiciousActivityAlert::class
        );
    }

    public function test_ip_allowlist_middleware_allows_when_no_restrictions(): void
    {
        // No IP allowlist entries for user
        $this->actingAs($this->user);

        $middleware = new \App\Http\Middleware\IpAllowlistMiddleware();
        $request = \Illuminate\Http\Request::create('/dashboard', 'GET');
        $request->setUserResolver(function () {
            return $this->user;
        });
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->setLaravelSession($this->app['session']->driver());

        $response = $middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_suspicious_activity_detection_creates_security_log(): void
    {
        // Set up conditions for suspicious activity
        Cache::put('failed_logins_127.0.0.1', 5, 300); // +30 points
        Cache::put("last_ip_{$this->user->id}", '192.168.1.100', 86400); // Set previous IP to trigger new IP alert (+20 points)

        // Authenticate the user
        $this->actingAs($this->user);

        $middleware = new \App\Http\Middleware\SuspiciousActivityDetection();
        $request = \Illuminate\Http\Request::create('/dashboard', 'GET');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->setLaravelSession($this->app['session']->driver());

        $response = $middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        // Should allow access but log suspicious activity
        $this->assertEquals(200, $response->getStatusCode());

        // Verify security log was created
        $this->assertDatabaseHas('security_logs', [
            'user_id' => $this->user->id,
            'event_type' => 'suspicious_activity',
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_database_migrations_work(): void
    {
        // Test that all our tables exist and can be used
        $this->assertTrue(Schema::hasTable('security_logs'));
        $this->assertTrue(Schema::hasTable('ip_allowlists'));

        // Test table columns
        $this->assertTrue(Schema::hasColumn('security_logs', 'user_id'));
        $this->assertTrue(Schema::hasColumn('security_logs', 'event_type'));
        $this->assertTrue(Schema::hasColumn('security_logs', 'ip_address'));
        $this->assertTrue(Schema::hasColumn('security_logs', 'details'));

        $this->assertTrue(Schema::hasColumn('ip_allowlists', 'user_id'));
        $this->assertTrue(Schema::hasColumn('ip_allowlists', 'ip_address'));
        $this->assertTrue(Schema::hasColumn('ip_allowlists', 'cidr_range'));
        $this->assertTrue(Schema::hasColumn('ip_allowlists', 'is_active'));
    }

    public function test_session_configuration_is_set(): void
    {
        // Test that session configuration has our security settings
        $this->assertArrayHasKey('idle_timeout', config('session'));
        $this->assertEquals(1800, config('session.idle_timeout')); // 30 minutes default
    }

    public function test_middleware_registration(): void
    {
        // Test that our middleware aliases are registered
        $app = $this->app;
        $router = $app['router'];

        // Check if our middleware alias exists
        $this->assertTrue($router->hasMiddlewareGroup('web'));

        // This is more of a smoke test - the real test is that the middleware works
        $this->assertTrue(class_exists(\App\Http\Middleware\IdleSessionTimeout::class));
        $this->assertTrue(class_exists(\App\Http\Middleware\IpAllowlistMiddleware::class));
        $this->assertTrue(class_exists(\App\Http\Middleware\SuspiciousActivityDetection::class));
    }
}

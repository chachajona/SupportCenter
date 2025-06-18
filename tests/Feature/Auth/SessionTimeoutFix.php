<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\SetupStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTimeoutFix extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure mail to use array driver for testing (prevents actual email sending)
        config(['mail.default' => 'array']);

        // Mark setup as completed for testing
        SetupStatus::markCompleted('database_migration');
        SetupStatus::markCompleted('roles_seeded');
        SetupStatus::markCompleted('admin_created');
        SetupStatus::markCompleted('setup_completed');

        // Create setup lock file to skip middleware checks
        $setupLockFile = storage_path('app/setup.lock');
        $lockData = [
            'completed_at' => now()->toISOString(),
            'completed_by' => 'test_environment',
            'version' => config('app.version', '1.0.0'),
        ];
        file_put_contents($setupLockFile, json_encode($lockData));
    }

    protected function tearDown(): void
    {
        // Clean up setup lock file after test
        $setupLockFile = storage_path('app/setup.lock');
        if (file_exists($setupLockFile)) {
            unlink($setupLockFile);
        }

        parent::tearDown();
    }

    /**
     * Test that users don't get immediate session timeout after login.
     */
    public function test_no_immediate_session_timeout_after_login(): void
    {
        $user = User::factory()->create();

        // Login the user
        $loginResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $loginResponse->assertRedirect(route('dashboard', absolute: false));

        // Immediately try to access a protected route
        $dashboardResponse = $this->get(route('dashboard'));

        // Should NOT get session expired message
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertDontSee('Session expired due to inactivity');

        // Verify we're still authenticated
        $this->assertAuthenticated();
    }

    /**
     * Test that users don't get immediate session timeout after registration.
     */
    public function test_no_immediate_session_timeout_after_registration(): void
    {
        $registrationResponse = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $registrationResponse->assertRedirect(route('dashboard'));

        // Follow the redirect to the dashboard
        $dashboardResponse = $this->followingRedirects()->get(route('dashboard'));

        // Should NOT get session expired message
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertDontSee('Session expired due to inactivity');

        // Verify we're still authenticated
        $this->assertAuthenticated();
    }

    /**
     * Test that last_activity_time is properly set in session after login.
     */
    public function test_last_activity_time_is_set_after_login(): void
    {
        $user = User::factory()->create();

        // Ensure last_activity_time doesn't exist initially
        $this->assertNull(session('last_activity_time'));

        // Login the user
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();

        // Access a protected route to trigger the middleware
        $this->get(route('dashboard'));

        // Verify last_activity_time is now set
        $this->assertNotNull(session('last_activity_time'));
        $this->assertIsInt(session('last_activity_time'));

        // Should be recent (within last 5 seconds)
        $this->assertGreaterThan(time() - 5, session('last_activity_time'));
    }
}

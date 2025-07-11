<?php

namespace Tests;

use App\Models\SetupStatus;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\RateLimiter;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any existing rate limiting
        RateLimiter::clear('setup');
    }

    /**
     * Complete setup for testing purposes to avoid middleware re-directions.
     * Call this manually in tests that need setup to be marked as completed.
     */
    protected function completeSetupForTesting(): void
    {
        try {
            // Mark essential setup steps as completed
            SetupStatus::markCompleted('database_migration');
            SetupStatus::markCompleted('roles_seeded');
            SetupStatus::markCompleted('permissions_seeded');
            SetupStatus::markCompleted('admin_created');
            SetupStatus::markCompleted('setup_completed');

            // Create setup lock file for middleware
            $setupLockFile = storage_path('app/setup.lock');
            if (! file_exists(dirname($setupLockFile))) {
                mkdir(dirname($setupLockFile), 0755, true);
            }
            file_put_contents($setupLockFile, json_encode([
                'completed' => true,
                'timestamp' => now()->toISOString(),
                'version' => '1.0.0',
            ]));
        } catch (\Exception $e) {
            // Ignore setup completion errors in testing
        }
    }

    /**
     * Reset setup state for tests that specifically need to test setup flow.
     */
    protected function resetSetupForTesting(): void
    {
        try {
            // Remove setup lock file
            $setupLockFile = storage_path('app/setup.lock');
            if (file_exists($setupLockFile)) {
                unlink($setupLockFile);
            }

            // Clear setup status records
            SetupStatus::whereIn('step', [
                'database_migration',
                'roles_seeded',
                'permissions_seeded',
                'admin_created',
                'setup_completed',
            ])->delete();
        } catch (\Exception $e) {
            // Ignore cleanup errors in testing
        }
    }

    /**
     * Skip setup completion for tests that need to test the setup flow itself.
     */
    protected function skipSetupCompletion(): void
    {
        $this->resetSetupForTesting();
    }

    /**
     * Set up an authenticated session for testing.
     */
    protected function setUpAuthenticationSession(): void
    {
        session(['auth.password_confirmed_at' => time()]);
    }

    /**
     * Set up a password confirmed session for testing.
     */
    protected function setUpPasswordConfirmedSession(): void
    {
        session(['auth.password_confirmed_at' => time()]);
    }
}

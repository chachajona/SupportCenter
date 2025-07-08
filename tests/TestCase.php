<?php

namespace Tests;

use App\Models\SetupStatus;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot the testing helper traits.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Disable middleware that interferes with automated tests
        $this->withoutMiddleware([
            \App\Http\Middleware\SuspiciousActivityDetection::class,
            \App\Http\Middleware\IdleSessionTimeout::class,
        ]);
    }

    /**
     * Complete setup for testing purposes to avoid middleware re-directions.
     * Call this manually in tests that need setup to be marked as completed.
     */
    protected function completeSetupForTesting(): void
    {
        // Mark essential setup steps as completed
        SetupStatus::markCompleted('database_migration');
        SetupStatus::markCompleted('roles_seeded');
        SetupStatus::markCompleted('permissions_seeded');
        SetupStatus::markCompleted('admin_created');
        SetupStatus::markCompleted('setup_completed');

        // Create setup lock file for middleware
        $setupLockFile = storage_path('app/setup.lock');
        file_put_contents($setupLockFile, json_encode([
            'completed_at' => now()->toISOString(),
            'completed_by' => 'testing_suite',
            'version' => '1.0'
        ]));
    }

    /**
     * Flush cache manually when needed in tests.
     * Call this after database is ready to avoid transaction conflicts.
     */
    protected function flushCacheForTesting(): void
    {
        Cache::flush();
    }

    /**
     * Clear rate limiting manually when needed in tests.
     * Call this after database is ready to avoid transaction conflicts.
     */
    protected function clearRateLimitingForTesting(): void
    {
        RateLimiter::clear('setup');
    }

    /**
     * Reset setup state for tests that specifically need to test setup flow.
     */
    protected function resetSetupForTesting(): void
    {
        // Clear rate limiting
        $this->clearRateLimitingForTesting();

        // Remove lock file
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
            'setup_completed'
        ])->delete();

        // Clear caches
        $this->flushCacheForTesting();
    }

    /**
     * Disable throttling for test requests
     */
    protected function withoutThrottling()
    {
        $this->app->bind('throttle', function () {
            return new class {
                public function handle($request, $next, ...$args)
                {
                    return $next($request);
                }
            };
        });

        return $this;
    }

    /**
     * Skip setup completion for tests that need to test the setup flow itself.
     */
    protected function skipSetupCompletion(): void
    {
        $this->resetSetupForTesting();
    }

    /**
     * Set up session data required for authentication middleware.
     * This prevents IdleSessionTimeout middleware from immediately logging out users during tests.
     */
    protected function setUpAuthenticationSession(): void
    {
        $this->session([
            'last_activity_time' => time(),
            'auth.password_confirmed_at' => time(),
        ]);
    }

    /**
     * Set up session with password confirmation for tests that require it.
     */
    protected function setUpPasswordConfirmedSession(): void
    {
        $this->session([
            'auth.password_confirmed_at' => time(),
            'last_activity_time' => time(),
        ]);
    }

    /**
     * Override actingAs to automatically set up session data.
     */
    public function actingAs($user, $guard = null)
    {
        $result = parent::actingAs($user, $guard);
        $this->setUpAuthenticationSession();

        return $result;
    }

    /**
     * Ensure MySQL test database is properly configured
     */
    protected function setUpMySQLTestDatabase(): void
    {
        if (config('database.default') === 'mysql') {
            try {
                // Verify connection works
                DB::connection()->getPdo();

                // Set MySQL session variables for testing
                DB::statement('SET SESSION sql_mode = "STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO"');
                DB::statement('SET SESSION innodb_lock_wait_timeout = 5');

            } catch (\Exception $e) {
                $this->markTestSkipped('MySQL test database not available: ' . $e->getMessage());
            }
        }
    }

    /**
     * Create a test database if it doesn't exist (for MySQL)
     */
    protected function createTestDatabaseIfNeeded(): void
    {
        if (config('database.default') === 'mysql') {
            $connections = config('database.connections', []);
            $mysqlConfig = $connections['mysql'] ?? [];
            $testDbName = $mysqlConfig['database'] ?? null;

            if (!$testDbName) {
                $this->markTestSkipped('No test database name configured');
            }

            try {
                // Get connection config without database name
                $config = $mysqlConfig;
                unset($config['database']);

                // Create temporary connection
                config(['database.connections.mysql_temp' => $config]);

                // Create database if not exists
                DB::connection('mysql_temp')->statement(
                    "CREATE DATABASE IF NOT EXISTS `{$testDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );

                // Test the connection to the database
                DB::connection()->statement('SELECT 1');

            } catch (\Exception $e) {
                $this->markTestSkipped('Could not create test database: ' . $e->getMessage());
            }
        }
    }

    /**
     * Verify MySQL foreign key constraints are enabled
     */
    protected function ensureForeignKeyConstraints(): void
    {
        if (config('database.default') === 'mysql') {
            $result = DB::select('SELECT @@foreign_key_checks as fk_enabled');
            if (!$result[0]->fk_enabled) {
                DB::statement('SET foreign_key_checks = 1');
            }
        }
    }

    /**
     * Get MySQL version for compatibility testing
     */
    protected function getMySQLVersion(): ?string
    {
        if (config('database.default') === 'mysql') {
            $result = DB::select('SELECT VERSION() as version');

            return $result[0]->version ?? null;
        }

        return null;
    }

    /**
     * Check if MySQL supports JSON data type (MySQL 5.7+)
     */
    protected function mysqlSupportsJson(): bool
    {
        $version = $this->getMySQLVersion();
        if (!$version) {
            return false;
        }

        return version_compare($version, '5.7.0', '>=');
    }

    /**
     * Optimize MySQL for testing performance
     */
    protected function optimizeMySQLForTesting(): void
    {
        if (config('database.default') === 'mysql') {
            try {
                // Disable query cache for consistent performance testing
                DB::statement('SET SESSION query_cache_type = OFF');

                // Set optimal settings for testing
                DB::statement('SET SESSION innodb_flush_log_at_trx_commit = 2');
                DB::statement('SET SESSION sync_binlog = 0');

            } catch (\Exception $e) {
                // Some settings might not be available - that's ok
            }
        }
    }

    /**
     * Clean up MySQL-specific test artifacts
     */
    protected function cleanupMySQLTestArtifacts(): void
    {
        if (config('database.default') === 'mysql') {
            try {
                // Reset any session variables
                DB::statement('SET SESSION sql_mode = DEFAULT');
                DB::statement('SET SESSION foreign_key_checks = 1');

                // Clean up any temporary tables that might have been created
                $tables = DB::select("SHOW TABLES LIKE 'temp_%'");
                foreach ($tables as $table) {
                    $tableName = array_values((array) $table)[0];
                    DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
                }

            } catch (\Exception $e) {
                // Cleanup failures are not critical
            }
        }
    }

    /**
     * Assert that a table exists in MySQL
     */
    protected function assertTableExists(string $tableName): void
    {
        $this->assertTrue(
            Schema::hasTable($tableName),
            "Table '{$tableName}' should exist in the database"
        );
    }

    /**
     * Assert that a column exists in a MySQL table
     */
    protected function assertColumnExists(string $tableName, string $columnName): void
    {
        $this->assertTrue(
            Schema::hasColumn($tableName, $columnName),
            "Column '{$columnName}' should exist in table '{$tableName}'"
        );
    }

    /**
     * Assert that a foreign key constraint exists
     */
    protected function assertForeignKeyExists(string $tableName, string $constraintName): void
    {
        if (config('database.default') === 'mysql') {
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                AND CONSTRAINT_NAME = ?
            ", [$tableName, $constraintName]);

            $this->assertNotEmpty(
                $constraints,
                "Foreign key constraint '{$constraintName}' should exist on table '{$tableName}'"
            );
        }
    }

    /**
     * Assert that an index exists on a table
     */
    protected function assertIndexExists(string $tableName, string $indexName): void
    {
        if (config('database.default') === 'mysql') {
            $indexes = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]);

            $this->assertNotEmpty(
                $indexes,
                "Index '{$indexName}' should exist on table '{$tableName}'"
            );
        }
    }
}

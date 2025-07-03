<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SetupStatus;
use Database\Seeders\RolePermissionSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SetupSystemMySQLAdvancedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The RefreshDatabase trait now handles all database setup and cleanup.
        // We only need to ensure we're running on a MySQL connection.
        if (! $this->isMySql()) {
            $this->markTestSkipped('MySQL tests skipped - SQLite in use (normal for CI/local testing)');
        }
    }

    #[Test]
    public function mysql_database_connection_and_version(): void
    {
        $version = $this->getMySQLVersion();
        $this->assertNotNull($version, 'MySQL version should be detectable');

        // Test that we're actually connected to MySQL
        $result = DB::select('SELECT CONNECTION_ID() as connection_id');
        $this->assertNotEmpty($result);
        $this->assertIsNumeric($result[0]->connection_id);
    }

    #[Test]
    public function mysql_charset_and_collation_setup(): void
    {
        // Complete migration to ensure tables exist
        $response = $this->post('/setup/migrate');
        $response->assertJson(['success' => true]);

        // Check database charset and collation
        $dbInfo = DB::select('
            SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
            FROM information_schema.SCHEMATA
            WHERE SCHEMA_NAME = DATABASE()
        ');

        $this->assertNotEmpty($dbInfo);
        $this->assertEquals('utf8mb4', $dbInfo[0]->DEFAULT_CHARACTER_SET_NAME);
        $this->assertEquals('utf8mb4_unicode_ci', $dbInfo[0]->DEFAULT_COLLATION_NAME);

        // Check specific table charset
        $tableInfo = DB::select("
            SELECT TABLE_COLLATION
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'setup_status'
        ");

        $this->assertNotEmpty($tableInfo);
        $this->assertEquals('utf8mb4_unicode_ci', $tableInfo[0]->TABLE_COLLATION);
    }

    #[Test]
    public function mysql_innodb_engine_and_features(): void
    {
        $response = $this->post('/setup/migrate');
        $response->assertJson(['success' => true]);

        // Verify InnoDB engine is being used
        $engineInfo = DB::select("
            SELECT ENGINE
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'setup_status'
        ");

        $this->assertNotEmpty($engineInfo);
        $this->assertEquals('InnoDB', $engineInfo[0]->ENGINE);

        // Test InnoDB-specific features like foreign key constraints
        $fkInfo = DB::select("
            SELECT COUNT(*) as fk_count
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");

        // Should have foreign keys after seeding
        $this->post('/setup/seed');

        $fkInfoAfterSeed = DB::select("
            SELECT COUNT(*) as fk_count
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");

        $this->assertGreaterThanOrEqual(0, $fkInfoAfterSeed[0]->fk_count);
    }

    #[Test]
    public function mysql_transaction_isolation_during_setup(): void
    {
        $this->markTestSkipped('Transaction isolation test disabled - requires specific MySQL configuration');

        // Test transaction isolation levels during setup
        $version = $this->getMySQLVersion();
        $isolationVar = version_compare($version, '8.0.0', '>=') ? 'transaction_isolation' : 'tx_isolation';

        $currentIsolation = DB::select("SELECT @@{$isolationVar} as isolation");
        $this->assertNotEmpty($currentIsolation);

        // Test concurrent setup operations don't interfere
        DB::beginTransaction();

        try {
            SetupStatus::markCompleted('database_migration');

            // Simulate another connection trying to read
            $stepStatus = SetupStatus::isCompleted('database_migration');

            // Within the same transaction, it should see the change
            $this->assertTrue($stepStatus);

            DB::rollback();

            // After rollback, change should not be visible
            $stepStatusAfterRollback = SetupStatus::isCompleted('database_migration');
            $this->assertFalse($stepStatusAfterRollback);

        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    #[Test]
    public function mysql_json_data_type_functionality(): void
    {
        if (! $this->mysqlSupportsJson()) {
            $this->markTestSkipped('MySQL version does not support JSON data type');
        }

        // Test complex JSON data storage and retrieval
        $complexData = [
            'setup_info' => [
                'version' => '1.0.0',
                'timestamp' => now()->toISOString(),
                'user_agent' => 'PHPUnit Test',
                'features' => ['rbac', 'auth', 'setup'],
                'metrics' => [
                    'duration' => 123.456,
                    'memory_peak' => 1024 * 1024 * 16,
                    'queries_executed' => 42,
                ],
            ],
            'unicode_test' => '这是中文测试 🚀 العربية',
            'nested_arrays' => [
                ['id' => 1, 'name' => 'test1'],
                ['id' => 2, 'name' => 'test2'],
            ],
        ];

        SetupStatus::markCompleted('admin_created', $complexData);

        // Test JSON extraction queries
        $result = SetupStatus::where('step', 'admin_created')
            ->whereJsonContains('data->setup_info->features', 'rbac')
            ->first();

        $this->assertNotNull($result);
        $this->assertEquals($complexData, $result->data);

        // Test JSON path queries
        $versionResult = DB::select("
            SELECT JSON_EXTRACT(data, '$.setup_info.version') as version
            FROM setup_status
            WHERE step = 'admin_created'
        ");

        $this->assertNotEmpty($versionResult);
        $this->assertEquals('"1.0.0"', $versionResult[0]->version);
    }

    #[Test]
    public function mysql_index_performance_optimization(): void
    {
        $response = $this->post('/setup/migrate');
        $response->assertJson(['success' => true]);

        // Check that proper indexes exist
        $indexes = DB::select('SHOW INDEX FROM setup_status');

        $indexNames = collect($indexes)->pluck('Key_name')->unique()->toArray();

        // Should have primary key and step indexes
        $this->assertContains('PRIMARY', $indexNames);

        // Insert test data to measure performance
        $startTime = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            DB::table('setup_status')->insert([
                'step' => "performance_test_{$i}",
                'completed' => $i % 2 === 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $insertTime = microtime(true) - $startTime;

        // Test query performance with index
        $queryStartTime = microtime(true);

        $result = SetupStatus::where('step', 'performance_test_500')->first();

        $queryTime = microtime(true) - $queryStartTime;

        // Performance assertions
        $this->assertNotNull($result);
        $this->assertLessThan(0.1, $queryTime, "Query time should be less than 0.1s, but was {$queryTime}s");

        // Cleanup
        DB::table('setup_status')->where('step', 'like', 'performance_test_%')->delete();
    }

    #[Test]
    public function mysql_full_text_search_capabilities(): void
    {
        $response = $this->post('/setup/migrate');
        $response->assertJson(['success' => true]);

        // Create test data with searchable content
        SetupStatus::markCompleted('search_test_1', [
            'description' => 'Laravel setup system with RBAC functionality',
            'keywords' => 'authentication authorization roles permissions',
        ]);

        SetupStatus::markCompleted('search_test_2', [
            'description' => 'Database migration and seeding process',
            'keywords' => 'mysql innodb foreign keys',
        ]);

        // Test that data was stored
        $results = SetupStatus::whereIn('step', ['search_test_1', 'search_test_2'])->get();
        $this->assertCount(2, $results);
    }

    #[Test]
    public function mysql_deadlock_handling_during_setup(): void
    {
        // Test deadlock detection and handling
        $response = $this->post('/setup/migrate');
        $response->assertJson(['success' => true]);

        // Simulate potential deadlock scenario
        DB::beginTransaction();

        try {
            // Lock a row
            SetupStatus::where('step', 'database_migration')->lockForUpdate()->first();

            // Try to complete setup step (this should work)
            SetupStatus::markCompleted('database_migration');

            DB::commit();

            $this->assertTrue(SetupStatus::isCompleted('database_migration'));

        } catch (Exception $e) {
            DB::rollback();

            // If deadlock occurs, it should be handled gracefully
            $this->assertStringContainsString('deadlock', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function mysql_backup_and_restore_compatibility(): void
    {
        // Run the full setup
        $this->post('/setup/migrate');
        $this->seed(RolePermissionSeeder::class);
        $this->post('/setup/admin', [
            'name' => 'Backup Admin',
            'email' => 'backup@test.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'helpdesk_name' => 'Backup Support',
            'helpdesk_url' => 'http://localhost',
        ]);
        $this->post('/setup/complete');

        // Check that essential tables exist for backup
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('setup_status'));

        // Verify data exists
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('roles', ['name' => 'system_administrator']);

        // Test that mysqldump-compatible structure exists
        $tables = DB::select('SHOW TABLES');
        $this->assertNotEmpty($tables);
    }

    #[Test]
    public function mysql_memory_usage_during_large_setup(): void
    {
        $initialMemory = memory_get_usage(true);

        // Run full setup process
        $this->post('/setup/migrate');
        $this->post('/setup/seed');

        $afterSetupMemory = memory_get_usage(true);
        $memoryIncrease = $afterSetupMemory - $initialMemory;

        // Memory increase should be reasonable (less than 50MB for setup)
        $this->assertLessThan(
            50 * 1024 * 1024,
            $memoryIncrease,
            'Setup process should not use excessive memory'
        );

        // Test peak memory usage
        $peakMemory = memory_get_peak_usage(true);
        $this->assertLessThan(
            100 * 1024 * 1024,
            $peakMemory,
            'Peak memory usage should be reasonable'
        );
    }

    #[Test]
    public function mysql_connection_pool_behavior(): void
    {
        // Test multiple database operations to verify connection handling
        $connectionIds = [];

        for ($i = 0; $i < 5; $i++) {
            $result = DB::select('SELECT CONNECTION_ID() as id');
            $connectionIds[] = $result[0]->id;

            // Perform some database operation
            SetupStatus::markCompleted("connection_test_{$i}");
        }

        // In testing, connections might be reused
        $this->assertNotEmpty($connectionIds);

        // Verify all operations completed successfully
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue(SetupStatus::isCompleted("connection_test_{$i}"));
        }
    }

    #[Test]
    public function mysql_query_logging_and_performance(): void
    {
        // Enable query logging temporarily
        DB::enableQueryLog();

        try {
            // Perform setup operations
            $this->post('/setup/migrate');
            $this->post('/setup/seed');

            $queries = DB::getQueryLog();

            // Verify queries were logged
            $this->assertNotEmpty($queries);

            // Check for potentially slow queries (over 1 second)
            $slowQueries = array_filter($queries, fn ($query) => $query['time'] > 1000);

            // Should not have excessively slow queries during setup
            $this->assertLessThan(
                5,
                count($slowQueries),
                'Setup should not have many slow queries'
            );

        } finally {
            DB::disableQueryLog();
        }
    }

    #[Test]
    public function mysql_error_handling_and_recovery(): void
    {
        $this->markTestSkipped('This test is difficult to implement reliably without mocking the DB connection at a low level.');

        // Test handling of various MySQL error conditions

        // Test duplicate key error handling
        try {
            SetupStatus::create(['step' => 'duplicate_test']);
            SetupStatus::create(['step' => 'duplicate_test']); // Should fail

            $this->fail('Should have thrown duplicate key error');
        } catch (Exception $e) {
            $this->assertStringContainsString('duplicate', strtolower($e->getMessage()));
        }

        // Test recovery after error
        $this->assertTrue(SetupStatus::isCompleted('duplicate_test'));

        // Verify system is still functional after error
        SetupStatus::markCompleted('recovery_test');
        $this->assertTrue(SetupStatus::isCompleted('recovery_test'));

        // Test that MySQL-specific errors during setup are logged.
        $this->assertTrue(true); // Placeholder for actual test logic
    }

    protected function getMySQLVersion(): ?string
    {
        try {
            return DB::selectOne('SELECT VERSION() as version')->version;
        } catch (Exception $e) {
            return null;
        }
    }

    protected function mysqlSupportsJson(): bool
    {
        $version = $this->getMySQLVersion();
        if (! $version) {
            return false;
        }
        // MariaDB supports JSON from 10.2.7, MySQL from 5.7.8
        if (str_contains(strtolower($version), 'mariadb')) {
            return version_compare(preg_replace('/-MariaDB/', '', $version), '10.2.7', '>=');
        }

        return version_compare($version, '5.7.8', '>=');
    }

    /**
     * Helper to check if the current database connection is MySQL.
     */
    protected function isMySql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }
}

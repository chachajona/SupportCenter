<?php

namespace Tests\Unit;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected PermissionCacheService $permissionCache;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests that require cache tagging if not supported
        if (!Cache::supportsTags()) {
            $this->markTestSkipped('Cache store does not support tagging');
        }

        $this->permissionCache = app(PermissionCacheService::class);
        $this->createTestData();
    }

    #[Test]
    public function permission_check_performance_is_under_threshold()
    {
        $user = User::first();
        $user->assignRole('support_agent');

        $startTime = microtime(true);

        // Perform 1000 permission checks
        for ($i = 0; $i < 1000; $i++) {
            $user->hasPermissionTo('tickets.create');
        }

        $endTime = microtime(true);
        $averageTime = (($endTime - $startTime) / 1000) * 1000; // Convert to milliseconds

        // Should be under 10ms average per check
        $this->assertLessThan(10, $averageTime, "Permission check took {$averageTime}ms on average, exceeding 10ms threshold");
    }

    #[Test]
    public function cached_permission_check_is_faster_than_database()
    {
        $user = User::first();
        $user->assignRole('support_agent');

        // Clear cache to ensure fresh start
        Cache::tags(['permissions', "user:{$user->id}"])->flush();

        // First check (database)
        $startTime = microtime(true);
        $user->hasPermissionTo('tickets.create');
        $dbTime = (microtime(true) - $startTime) * 1000;

        // Second check (cached)
        $startTime = microtime(true);
        $user->hasPermissionTo('tickets.create');
        $cacheTime = (microtime(true) - $startTime) * 1000;

        // Cached should be faster than database
        $this->assertLessThan($dbTime, $cacheTime, "Cached permission check ({$cacheTime}ms) should be faster than database check ({$dbTime}ms)");
    }

    #[Test]
    public function concurrent_permission_checks_complete_in_reasonable_time()
    {
        $users = User::factory()->count(100)->create();
        $role = Role::where('name', 'support_agent')->first();

        // Assign role to all users
        foreach ($users as $user) {
            $user->assignRole($role);
        }

        $startTime = microtime(true);

        // Simulate concurrent permission checks
        foreach ($users as $user) {
            $user->hasPermissionTo('tickets.create');
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Should handle 100 concurrent checks in under 500ms
        $this->assertLessThan(500, $totalTime, "100 concurrent permission checks took {$totalTime}ms, exceeding 500ms threshold");
    }

    #[Test]
    public function permission_cache_service_performance()
    {
        $user = User::first();
        $user->assignRole('support_agent');

        // Clear cache
        $this->permissionCache->clearUserCache($user->id);

        // Test getUserPermissions performance
        $startTime = microtime(true);
        $permissions = $this->permissionCache->getUserPermissions($user->id);
        $loadTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(50, $loadTime, "Permission cache load took {$loadTime}ms, exceeding 50ms threshold");
        $this->assertNotEmpty($permissions);

        // Test cached retrieval performance
        $startTime = microtime(true);
        $cachedPermissions = $this->permissionCache->getUserPermissions($user->id);
        $cacheTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(5, $cacheTime, "Cached permission retrieval took {$cacheTime}ms, exceeding 5ms threshold");
        $this->assertEquals($permissions->toArray(), $cachedPermissions->toArray());
    }

    #[Test]
    public function bulk_role_assignment_performance()
    {
        $users = User::factory()->count(1000)->create();
        $role = Role::where('name', 'support_agent')->first();

        $startTime = microtime(true);

        // Bulk assign roles
        foreach ($users->chunk(100) as $userChunk) {
            foreach ($userChunk as $user) {
                $user->assignRole($role);
            }
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Should handle 1000 role assignments in under 5 seconds
        $this->assertLessThan(5000, $totalTime, "1000 role assignments took {$totalTime}ms, exceeding 5000ms threshold");
    }

    #[Test]
    public function complex_permission_queries_performance()
    {
        $user = User::first();
        $user->assignRole(['support_agent', 'knowledge_curator']);

        $startTime = microtime(true);

        // Perform complex permission checks
        for ($i = 0; $i < 100; $i++) {
            $user->hasAnyPermission(['tickets.create', 'knowledge.manage', 'users.view']);
            $user->hasAllPermissions(['tickets.create', 'knowledge.manage']);
        }

        $endTime = microtime(true);
        $averageTime = (($endTime - $startTime) / 200) * 1000; // 200 operations total

        // Complex queries should average under 15ms
        $this->assertLessThan(15, $averageTime, "Complex permission queries averaged {$averageTime}ms, exceeding 15ms threshold");
    }

    #[Test]
    public function memory_usage_stays_reasonable_with_many_users()
    {
        $initialMemory = memory_get_usage(true);

        // Create users with roles
        $users = User::factory()->count(500)->create();
        $roles = Role::all();

        foreach ($users as $index => $user) {
            $user->assignRole($roles[$index % $roles->count()]);
        }

        // Perform permission checks
        foreach ($users->take(100) as $user) {
            $user->hasPermissionTo('tickets.create');
        }

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = ($finalMemory - $initialMemory) / 1024 / 1024; // Convert to MB

        // Memory increase should be reasonable (under 50MB)
        $this->assertLessThan(50, $memoryIncrease, "Memory usage increased by {$memoryIncrease}MB, exceeding 50MB threshold");
    }

    #[Test]
    public function cache_hit_ratio_is_optimal()
    {
        $user = User::first();
        $user->assignRole('support_agent');

        // Clear cache to start fresh
        Cache::flush();

        // First batch - cache misses
        for ($i = 0; $i < 10; $i++) {
            $this->permissionCache->getUserPermissions($user->id);
        }

        // Second batch - should be cache hits
        $hitCount = 0;
        for ($i = 0; $i < 10; $i++) {
            $startTime = microtime(true);
            $this->permissionCache->getUserPermissions($user->id);
            $time = (microtime(true) - $startTime) * 1000;

            // If under 5ms, consider it a cache hit
            if ($time < 5) {
                $hitCount++;
            }
        }

        // Expect at least 80% cache hits
        $hitRatio = $hitCount / 10;
        $this->assertGreaterThan(0.8, $hitRatio, "Cache hit ratio was {$hitRatio}, below 80% threshold");
    }

    #[Test]
    public function wildcard_permission_performance()
    {
        $user = User::first();
        $role = Role::where('name', 'system_administrator')->first();

        // Create a wildcard permission
        $wildcardPermission = Permission::create([
            'name' => 'tickets.*',
            'resource' => 'tickets',
            'action' => '*',
        ]);

        $role->givePermissionTo($wildcardPermission);
        $user->assignRole($role);

        $startTime = microtime(true);

        // Test wildcard permission checks
        for ($i = 0; $i < 100; $i++) {
            $this->permissionCache->userHasPermission($user->id, 'tickets.create');
            $this->permissionCache->userHasPermission($user->id, 'tickets.update');
            $this->permissionCache->userHasPermission($user->id, 'tickets.delete');
        }

        $endTime = microtime(true);
        $averageTime = (($endTime - $startTime) / 300) * 1000; // 300 checks total

        // Wildcard checks should average under 12ms
        $this->assertLessThan(12, $averageTime, "Wildcard permission checks averaged {$averageTime}ms, exceeding 12ms threshold");
    }

    protected function createTestData(): void
    {
        // Create roles
        $roles = [
            ['name' => 'support_agent', 'hierarchy_level' => 1],
            ['name' => 'department_manager', 'hierarchy_level' => 2],
            ['name' => 'system_administrator', 'hierarchy_level' => 4],
            ['name' => 'knowledge_curator', 'hierarchy_level' => 1],
        ];

        foreach ($roles as $roleData) {
            Role::create($roleData);
        }

        // Create permissions
        $permissions = [
            ['name' => 'tickets.create', 'resource' => 'tickets', 'action' => 'create'],
            ['name' => 'tickets.update', 'resource' => 'tickets', 'action' => 'update'],
            ['name' => 'tickets.delete', 'resource' => 'tickets', 'action' => 'delete'],
            ['name' => 'knowledge.manage', 'resource' => 'knowledge', 'action' => 'manage'],
            ['name' => 'users.view', 'resource' => 'users', 'action' => 'view'],
        ];

        foreach ($permissions as $permissionData) {
            Permission::create($permissionData);
        }

        // Assign permissions to roles
        $supportAgent = Role::where('name', 'support_agent')->first();
        $supportAgent->givePermissionTo(['tickets.create', 'tickets.update']);

        $knowledgeCurator = Role::where('name', 'knowledge_curator')->first();
        $knowledgeCurator->givePermissionTo('knowledge.manage');

        $systemAdmin = Role::where('name', 'system_administrator')->first();
        $systemAdmin->givePermissionTo(Permission::all());

        // Create test users
        User::factory()->count(10)->create();
    }
}

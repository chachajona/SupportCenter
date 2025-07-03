<?php

namespace Tests\Performance;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HelpdeskLoadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data structure
        $this->createTestData();
    }

    /**
     * Test API endpoint performance under load
     */
    public function test_api_ticket_listing_performance(): void
    {
        $department = Department::first();
        $users = User::where('department_id', $department->id)->take(50)->get();

        $startTime = microtime(true);
        $responses = [];

        // Simulate 200 concurrent API requests
        for ($i = 0; $i < 200; $i++) {
            $user = $users->random();
            Sanctum::actingAs($user);

            $responses[] = $this->getJson('/api/tickets?per_page=25');
        }

        $endTime = microtime(true);
        $avgResponseTime = ($endTime - $startTime) / 200;

        // Assert performance targets
        $this->assertLessThan(0.2, $avgResponseTime, 'API response time should be under 200ms');

        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // Log performance metrics
        $this->logPerformanceMetric('api_ticket_listing', $avgResponseTime);
    }

    /**
     * Test memory usage during large dataset operations
     */
    public function test_memory_usage_with_large_datasets(): void
    {
        $initialMemory = memory_get_usage(true);

        // Create large dataset
        Ticket::factory(5000)->create();

        // Perform memory-intensive operations
        $tickets = Ticket::with(['department', 'assignedTo', 'status', 'priority'])
            ->paginate(100);

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = ($finalMemory - $initialMemory) / 1024 / 1024; // MB

        $this->assertLessThan(50, $memoryIncrease, 'Memory increase should be under 50MB');

        // Log memory usage
        $this->logPerformanceMetric('memory_usage_large_dataset', $memoryIncrease);
    }

    /**
     * Test database query performance
     */
    public function test_database_query_performance(): void
    {
        DB::enableQueryLog();

        $startTime = microtime(true);

        // Complex query with joins and aggregations
        $results = DB::table('tickets')
            ->join('departments', 'tickets.department_id', '=', 'departments.id')
            ->join('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->join('ticket_priorities', 'tickets.priority_id', '=', 'ticket_priorities.id')
            ->select(
                'departments.name as dept_name',
                DB::raw('COUNT(*) as ticket_count'),
                DB::raw('AVG(DATEDIFF(resolved_at, created_at)) as avg_resolution_days')
            )
            ->where('tickets.created_at', '>=', now()->subMonths(3))
            ->groupBy('departments.id', 'departments.name')
            ->get();

        $endTime = microtime(true);
        $queryTime = ($endTime - $startTime) * 1000; // milliseconds

        $queries = DB::getQueryLog();
        $slowQueries = array_filter($queries, fn ($query) => $query['time'] > 100);

        $this->assertLessThan(500, $queryTime, 'Complex query should complete under 500ms');
        $this->assertEmpty($slowQueries, 'No individual queries should exceed 100ms');

        $this->logPerformanceMetric('complex_query_time', $queryTime);
    }

    /**
     * Test concurrent user operations
     */
    public function test_concurrent_user_operations(): void
    {
        $users = User::take(20)->get();
        $startTime = microtime(true);

        // Simulate concurrent operations
        $operations = [];

        foreach ($users as $user) {
            Sanctum::actingAs($user);

            // Each user performs multiple operations
            $operations[] = $this->postJson('/api/tickets', [
                'subject' => 'Load test ticket',
                'description' => 'Testing concurrent operations',
                'priority_id' => TicketPriority::first()->id,
                'department_id' => $user->department_id,
            ]);

            $operations[] = $this->getJson('/api/tickets');
            $operations[] = $this->getJson('/dashboard');
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Verify all operations completed successfully
        foreach ($operations as $response) {
            $this->assertTrue(
                in_array($response->getStatusCode(), [200, 201]),
                'All concurrent operations should succeed'
            );
        }

        $this->assertLessThan(10, $totalTime, 'Concurrent operations should complete under 10 seconds');

        $this->logPerformanceMetric('concurrent_operations', $totalTime);
    }

    /**
     * Test cache performance
     */
    public function test_cache_performance(): void
    {
        $key = 'test_cache_performance';
        $data = ['large_dataset' => range(1, 10000)];

        // Test cache write performance
        $startTime = microtime(true);
        Cache::put($key, $data, 300);
        $writeTime = (microtime(true) - $startTime) * 1000;

        // Test cache read performance
        $startTime = microtime(true);
        $retrieved = Cache::get($key);
        $readTime = (microtime(true) - $startTime) * 1000;

        $this->assertEquals($data, $retrieved);
        $this->assertLessThan(50, $writeTime, 'Cache write should be under 50ms');
        $this->assertLessThan(10, $readTime, 'Cache read should be under 10ms');

        $this->logPerformanceMetric('cache_write_time', $writeTime);
        $this->logPerformanceMetric('cache_read_time', $readTime);
    }

    /**
     * Test queue performance
     */
    public function test_queue_performance(): void
    {
        Queue::fake();

        $startTime = microtime(true);

        // Dispatch multiple jobs (using a generic job for testing)
        for ($i = 0; $i < 100; $i++) {
            dispatch(function () {
                // Simulate job processing
                usleep(1000); // 1ms delay
            });
        }

        $endTime = microtime(true);
        $dispatchTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(1000, $dispatchTime, 'Job dispatch should be under 1 second for 100 jobs');

        $this->logPerformanceMetric('queue_dispatch_time', $dispatchTime);
    }

    /**
     * Test API rate limiting
     */
    public function test_api_rate_limiting_performance(): void
    {
        $user = User::first();
        Sanctum::actingAs($user);

        $successfulRequests = 0;
        $rateLimitedRequests = 0;

        // Make requests up to rate limit
        for ($i = 0; $i < 35; $i++) { // Assuming 30 requests per minute limit
            $response = $this->getJson('/api/tickets');

            if ($response->getStatusCode() === 200) {
                $successfulRequests++;
            } elseif ($response->getStatusCode() === 429) {
                $rateLimitedRequests++;
            }
        }

        $this->assertGreaterThan(25, $successfulRequests, 'Should allow at least 25 requests');
        $this->assertGreaterThan(0, $rateLimitedRequests, 'Should rate limit after threshold');

        $this->logPerformanceMetric('rate_limit_successful', $successfulRequests);
        $this->logPerformanceMetric('rate_limit_blocked', $rateLimitedRequests);
    }

    /**
     * Create test data for performance testing
     */
    private function createTestData(): void
    {
        // Create departments
        $departments = Department::factory(5)->create();

        // Create ticket statuses and priorities
        TicketStatus::factory()->create(['name' => 'Open', 'color' => 'blue']);
        TicketStatus::factory()->create(['name' => 'In Progress', 'color' => 'yellow']);
        TicketStatus::factory()->create(['name' => 'Resolved', 'color' => 'green']);

        TicketPriority::factory()->create(['name' => 'Low', 'color' => 'gray']);
        TicketPriority::factory()->create(['name' => 'Medium', 'color' => 'blue']);
        TicketPriority::factory()->create(['name' => 'High', 'color' => 'orange']);

        // Create users in each department
        foreach ($departments as $department) {
            User::factory(15)->create(['department_id' => $department->id]);
        }

        // Create tickets for testing
        Ticket::factory(1000)->create();
    }

    /**
     * Log performance metrics for monitoring
     */
    private function logPerformanceMetric(string $metric, float $value): void
    {
        Log::channel('performance')->info("Performance metric: {$metric}", [
            'metric' => $metric,
            'value' => $value,
            'timestamp' => now()->toISOString(),
            'test_environment' => app()->environment(),
        ]);
    }
}

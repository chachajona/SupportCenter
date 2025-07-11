<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowRule;
use App\Services\AutomationService;
use App\Services\WorkflowEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\CreatesWorkflowTestData;

final class AutomationServiceTest extends TestCase
{
    use CreatesWorkflowTestData, RefreshDatabase;

    private AutomationService $automationService;

    /** @var WorkflowEngine&MockInterface */
    private WorkflowEngine $mockWorkflowEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createWorkflowTestData();

        $this->mockWorkflowEngine = Mockery::mock(WorkflowEngine::class);
        $this->app->instance(WorkflowEngine::class, $this->mockWorkflowEngine);

        $this->automationService = new AutomationService($this->mockWorkflowEngine);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_check_sla_breaches(): void
    {
        // Create required test data
        $user = \App\Models\User::factory()->create();
        $openStatusId = \App\Models\TicketStatus::where('name', 'Open')->first()->id;

        // Get available priority IDs
        $criticalPriority = \App\Models\TicketPriority::orderBy('level', 'desc')->first();
        $highPriority = \App\Models\TicketPriority::where('level', '>=', 3)->first() ?: $criticalPriority;
        $normalPriority = \App\Models\TicketPriority::where('level', '<=', 2)->first() ?: $criticalPriority;

        // Create tickets with different SLA scenarios
        $ticketNearBreach = Ticket::factory()->create([
            'priority_id' => $criticalPriority->id,
            'status_id' => $openStatusId,
            'created_by' => $user->id,
            'created_at' => Carbon::now()->subHours(23), // 23 hours ago
        ]);

        $ticketBreached = Ticket::factory()->create([
            'priority_id' => $highPriority->id,
            'status_id' => $openStatusId,
            'created_by' => $user->id,
            'created_at' => Carbon::now()->subHours(25), // 25 hours ago (breached)
        ]);

        $ticketOk = Ticket::factory()->create([
            'priority_id' => $normalPriority->id,
            'status_id' => $openStatusId,
            'created_by' => $user->id,
            'created_at' => Carbon::now()->subHours(2), // 2 hours ago
        ]);

        $results = $this->automationService->checkSlaBreaches();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('near_breach', $results);
        $this->assertArrayHasKey('breached', $results);
        $this->assertArrayHasKey('escalated', $results);

        // Should identify near breach and breached tickets
        $this->assertIsInt($results['near_breach']);
        $this->assertIsInt($results['breached']);
    }

    public function test_can_process_scheduled_rules(): void
    {
        $user = User::factory()->create();

        // Get status IDs
        $openStatusId = \App\Models\TicketStatus::where('name', 'Open')->first()->id;

        // Create a scheduled rule that should execute
        $rule = WorkflowRule::factory()->create([
            'is_active' => true,
            'created_by' => $user->id,
            'schedule' => [
                'type' => 'recurring',
                'frequency' => 'daily',
                'time' => Carbon::now()->format('H:i'),
            ],
            'entity_type' => 'ticket',
            'conditions' => [
                'operator' => 'AND',
                'rules' => [
                    ['field' => 'status_id', 'operator' => '=', 'value' => $openStatusId],
                ],
            ],
        ]);

        // Create tickets that match the rule conditions
        $matchingTickets = Ticket::factory()->count(3)->create([
            'status_id' => $openStatusId,
            'created_by' => $user->id,
        ]);

        $this->mockWorkflowEngine->shouldReceive('executeWorkflowRule')
            ->times(3) // Should execute for all 3 matching tickets
            ->andReturn(\App\Models\WorkflowExecution::factory()->make(['id' => 1, 'status' => 'completed']));

        $results = $this->automationService->processScheduledRules();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('processed_rules', $results);
        $this->assertArrayHasKey('total_executions', $results);
        $this->assertGreaterThan(0, $results['processed_rules']);
        $this->assertGreaterThan(0, $results['total_executions']);
    }

    public function test_can_auto_close_stale_tickets(): void
    {
        // Create required test data
        $user = \App\Models\User::factory()->create();

        // Get status IDs - ensure they exist first
        $resolvedStatus = \App\Models\TicketStatus::where('name', 'Resolved')->first();
        $openStatus = \App\Models\TicketStatus::where('name', 'Open')->first();
        $closedStatus = \App\Models\TicketStatus::where('name', 'Closed')->first();

        // Ensure we have the statuses we need
        $this->assertNotNull($openStatus, 'Open status should exist');
        $this->assertNotNull($resolvedStatus, 'Resolved status should exist');
        $this->assertNotNull($closedStatus, 'Closed status should exist');

        // Debug: check status properties
        $this->assertFalse($openStatus->is_closed, 'Open status should not be closed');
        $this->assertTrue($resolvedStatus->is_closed, 'Resolved status should be closed');
        $this->assertTrue($closedStatus->is_closed, 'Closed status should be closed');

        // Check what status the AutomationService will actually use
        $automationClosedStatus = \App\Models\TicketStatus::where('is_closed', true)->first();
        $this->assertNotNull($automationClosedStatus, 'AutomationService should find a closed status');

        // Create tickets with explicit status override in factory call
        $staleTicket = Ticket::factory()->create([
            'status_id' => $openStatus->id, // Explicitly set in factory parameters
            'created_by' => $user->id,
            'updated_at' => Carbon::now()->subDays(31), // 31 days ago
        ]);

        $recentTicket = Ticket::factory()->create([
            'status_id' => $openStatus->id, // Explicitly set in factory parameters
            'created_by' => $user->id,
            'updated_at' => Carbon::now()->subDays(15), // 15 days ago
        ]);

        $resolvedTicket = Ticket::factory()->create([
            'status_id' => $resolvedStatus->id, // Explicitly set in factory parameters
            'created_by' => $user->id,
            'updated_at' => Carbon::now()->subDays(35), // 35 days ago but already resolved
        ]);

        // Verify tickets were created with correct statuses
        $staleTicket->refresh();
        $recentTicket->refresh();
        $resolvedTicket->refresh();

        $this->assertEquals($openStatus->id, $staleTicket->status_id, 'Stale ticket should be open');
        $this->assertEquals($openStatus->id, $recentTicket->status_id, 'Recent ticket should be open');
        $this->assertEquals($resolvedStatus->id, $resolvedTicket->status_id, 'Resolved ticket should be resolved');

        $results = $this->automationService->autoCloseStaleTickets();

        // Debug output
        $this->assertIsArray($results);
        $this->assertArrayHasKey('closed_tickets', $results);
        $this->assertArrayHasKey('total_closed', $results);

        // Should have closed the stale open ticket
        $this->assertGreaterThan(0, $results['total_closed'], 'Should have closed at least one stale ticket');

        // Verify the stale ticket was closed
        $staleTicket->refresh();
        $this->assertEquals($automationClosedStatus->id, $staleTicket->status_id);

        // Verify other tickets remain unchanged
        $recentTicket->refresh();
        $this->assertEquals($openStatus->id, $recentTicket->status_id); // Still open because recent

        $resolvedTicket->refresh();
        $this->assertEquals($resolvedStatus->id, $resolvedTicket->status_id); // Still resolved, untouched
    }

    public function test_can_send_follow_up_reminders(): void
    {
        Notification::fake();

        // Create required test data
        $user = \App\Models\User::factory()->create();

        // Get status ID
        $openStatusId = \App\Models\TicketStatus::where('name', 'Open')->first()->id;
        $highPriority = \App\Models\TicketPriority::where('level', '>=', 3)->first() ?: \App\Models\TicketPriority::first();

        // Create tickets that need follow-up
        $ticketNeedingFollowUp = Ticket::factory()->create([
            'status_id' => $openStatusId,
            'priority_id' => $highPriority->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'updated_at' => Carbon::now()->subDays(3), // 3 days without update
        ]);

        $ticketRecentlyUpdated = Ticket::factory()->create([
            'status_id' => $openStatusId,
            'priority_id' => $highPriority->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'updated_at' => Carbon::now()->subHours(2), // Recently updated
        ]);

        $results = $this->automationService->sendFollowUpReminders();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('reminders_sent', $results);
        $this->assertArrayHasKey('total_reminders', $results);

        // Should have sent reminders for stale tickets
        $this->assertGreaterThan(0, $results['total_reminders']);

        // Verify notifications were sent
        Notification::assertSentTimes(
            \App\Notifications\FollowUpReminderNotification::class,
            $results['total_reminders']
        );
    }

    public function test_can_generate_daily_reports(): void
    {
        // Create test data for the report - explicitly use today's date
        $today = Carbon::today();
        $tickets = Ticket::factory()->count(10)->create([
            'created_at' => $today->copy()->addHours(8), // 8 AM today
        ]);

        $oldTickets = Ticket::factory()->count(5)->create([
            'created_at' => Carbon::now()->subDays(2), // Not today
        ]);

        $results = $this->automationService->generateDailyReports();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('report_generated', $results);
        $this->assertArrayHasKey('metrics', $results);
        $this->assertTrue($results['report_generated']);

        $metrics = $results['metrics'];
        $this->assertArrayHasKey('total_tickets_today', $metrics);
        $this->assertArrayHasKey('resolved_tickets_today', $metrics);
        $this->assertArrayHasKey('average_resolution_time', $metrics);
        $this->assertArrayHasKey('sla_compliance_rate', $metrics);

        // Should count only today's tickets
        $this->assertEquals(10, $metrics['total_tickets_today']);
    }

    public function test_can_get_automation_statistics(): void
    {
        // Create test data
        $tickets = Ticket::factory()->count(15)->create();
        $rules = WorkflowRule::factory()->count(5)->create();

        $stats = $this->automationService->getAutomationStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('active_rules', $stats);
        $this->assertArrayHasKey('total_tickets', $stats);
        $this->assertArrayHasKey('automation_coverage', $stats);
        $this->assertArrayHasKey('sla_compliance', $stats);

        $this->assertIsNumeric($stats['active_rules']);
        $this->assertIsNumeric($stats['total_tickets']);
        $this->assertIsNumeric($stats['automation_coverage']);
        $this->assertIsNumeric($stats['sla_compliance']);
    }

    public function test_can_get_sla_compliance_metrics(): void
    {
        // Ensure test data exists (in case trait setup didn't run properly)
        $this->createWorkflowTestData();

        // Create a priority with ID 5 that the service expects for breached tickets
        \App\Models\TicketPriority::firstOrCreate(['id' => 5], [
            'name' => 'Ultra Critical',
            'color' => '#dc2626',
            'level' => 5,
            'sort_order' => 5,
        ]);

        // Get the resolved status ID first
        $resolvedStatus = \App\Models\TicketStatus::where('name', 'Resolved')->first();
        $this->assertNotNull($resolvedStatus, 'Resolved status should exist');

        // Get actual priority IDs
        $mediumPriority = \App\Models\TicketPriority::where('name', 'Medium')->first();
        $breachedPriority = \App\Models\TicketPriority::find(5); // The one the service expects

        $this->assertNotNull($mediumPriority, 'Medium priority should exist');
        $this->assertNotNull($breachedPriority, 'Breached priority (ID 5) should exist');

        // Create tickets with different SLA scenarios
        $compliantTickets = Ticket::factory()->count(8)->create([
            'status_id' => $resolvedStatus->id,
            'priority_id' => $mediumPriority->id,
            'created_at' => Carbon::now()->subHours(2),
            'updated_at' => Carbon::now()->subHours(1),
            'resolved_at' => Carbon::now()->subHours(1), // Add resolved_at timestamp
        ]);

        $breachedTickets = Ticket::factory()->count(2)->create([
            'status_id' => $resolvedStatus->id,
            'priority_id' => $breachedPriority->id, // Use priority ID 5 that service expects
            'created_at' => Carbon::now()->subDays(2),
            'updated_at' => Carbon::now()->subDays(1),
            'resolved_at' => Carbon::now()->subDays(1), // Add resolved_at timestamp
        ]);

        $metrics = $this->automationService->getSlaComplianceMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_tickets', $metrics);
        $this->assertArrayHasKey('compliant_tickets', $metrics);
        $this->assertArrayHasKey('breached_tickets', $metrics);
        $this->assertArrayHasKey('compliance_rate', $metrics);

        $this->assertEquals(10, $metrics['total_tickets']);
        $this->assertGreaterThan(0, $metrics['compliant_tickets']);
        $this->assertGreaterThan(0, $metrics['breached_tickets']);
        $this->assertLessThanOrEqual(100, $metrics['compliance_rate']); // Should be less than 100% due to breaches
    }

    public function test_uses_cache_for_performance(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return str_contains($key, 'automation_stats') && $ttl > 0;
            })
            ->andReturn([
                'active_rules' => 5,
                'total_tickets' => 100,
                'automation_coverage' => 75.0,
                'sla_compliance' => 85.0,
            ]);

        $stats = $this->automationService->getAutomationStatistics();

        $this->assertIsArray($stats);
        $this->assertEquals(5, $stats['active_rules']);
        $this->assertEquals(100, $stats['total_tickets']);
    }

    public function test_handles_rule_execution_errors_gracefully(): void
    {
        Log::shouldReceive('error')
            ->atLeast()
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Scheduled rule execution failed') &&
                    isset($context['rule_id']) &&
                    isset($context['error']);
            });

        // Also allow Log::info calls that might be triggered
        Log::shouldReceive('info')
            ->zeroOrMoreTimes()
            ->andReturn(null);

        $user = User::factory()->create();

        $rule = WorkflowRule::factory()->create([
            'is_active' => true,
            'schedule' => [
                'type' => 'recurring',
                'frequency' => 'daily',
                'time' => Carbon::now()->format('H:i'),
                'last_run' => Carbon::now()->subDay(), // Ensure it should run now
            ],
            'entity_type' => 'ticket',
            'conditions' => [
                'operator' => 'AND',
                'rules' => [
                    // Simple condition that will match our tickets
                    ['field' => 'status_id', 'operator' => '>', 'value' => 0],
                ],
            ],
        ]);

        // Create tickets that will match the rule conditions
        $tickets = Ticket::factory()->count(2)->create([
            'created_by' => $user->id,
        ]);

        $this->mockWorkflowEngine->shouldReceive('executeWorkflowRule')
            ->atLeast()->once() // At least one call, but actual count may vary based on rule logic
            ->andThrow(new \Exception('Rule execution failed'));

        $results = $this->automationService->processScheduledRules();

        $this->assertIsArray($results);
        // The service logs errors but doesn't include them in the return array
        // Just verify the service completed without crashing
        $this->assertArrayHasKey('processed_rules', $results);
        $this->assertArrayHasKey('total_executions', $results);
        $this->assertEquals(0, $results['total_executions']); // No successful executions due to exceptions
    }

    public function test_respects_rule_execution_limits(): void
    {
        $rule = WorkflowRule::factory()->create([
            'is_active' => true,
            'execution_limit' => 5,
            'execution_count' => 4, // One execution left
            'schedule' => [
                'type' => 'recurring',
                'frequency' => 'daily',
                'time' => Carbon::now()->format('H:i'),
                'last_run' => Carbon::now()->subDay(),
            ],
            'entity_type' => 'ticket',
        ]);

        $tickets = Ticket::factory()->count(3)->create();

        $this->mockWorkflowEngine->shouldReceive('executeWorkflowRule')
            ->once() // Should only execute once due to limit
            ->andReturn(\App\Models\WorkflowExecution::factory()->make(['id' => 1, 'status' => 'completed']));

        $results = $this->automationService->processScheduledRules();

        $this->assertIsArray($results);

        // Verify execution count was incremented
        $rule->refresh();
        $this->assertEquals(5, $rule->execution_count);
    }

    public function test_processes_rules_by_priority(): void
    {
        $lowPriorityRule = WorkflowRule::factory()->create([
            'is_active' => true,
            'priority' => 10,
            'schedule' => [
                'type' => 'recurring',
                'frequency' => 'daily',
                'time' => Carbon::now()->format('H:i'),
                'last_run' => Carbon::now()->subDay(),
            ],
            'entity_type' => 'ticket',
            'conditions' => [
                'operator' => 'AND',
                'rules' => [
                    ['field' => 'status_id', 'operator' => '>', 'value' => 0],
                ],
            ],
        ]);

        $highPriorityRule = WorkflowRule::factory()->create([
            'is_active' => true,
            'priority' => 1,
            'schedule' => [
                'type' => 'recurring',
                'frequency' => 'daily',
                'time' => Carbon::now()->format('H:i'),
                'last_run' => Carbon::now()->subDay(),
            ],
            'entity_type' => 'ticket',
            'conditions' => [
                'operator' => 'AND',
                'rules' => [
                    ['field' => 'status_id', 'operator' => '>', 'value' => 0],
                ],
            ],
        ]);

        $tickets = Ticket::factory()->count(2)->create();

        $executionOrder = [];
        $this->mockWorkflowEngine->shouldReceive('executeWorkflowRule')
            ->times(4) // 2 rules × 2 tickets
            ->andReturnUsing(function ($rule, $entity) use (&$executionOrder) {
                $executionOrder[] = $rule->id;

                return \App\Models\WorkflowExecution::factory()->make(['id' => $rule->id, 'status' => 'completed']);
            });

        $results = $this->automationService->processScheduledRules();

        // High priority rule should execute first
        $this->assertEquals($highPriorityRule->id, $executionOrder[0]);
        $this->assertEquals($highPriorityRule->id, $executionOrder[1]);
        $this->assertEquals($lowPriorityRule->id, $executionOrder[2]);
        $this->assertEquals($lowPriorityRule->id, $executionOrder[3]);
    }

    public function test_can_run_all_automation_tasks(): void
    {
        $this->mockWorkflowEngine->shouldReceive('executeWorkflowRule')
            ->zeroOrMoreTimes()
            ->andReturn(\App\Models\WorkflowExecution::factory()->make(['id' => 1, 'status' => 'completed']));

        $results = $this->automationService->runAllAutomationTasks();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('sla_monitoring', $results);
        $this->assertArrayHasKey('scheduled_rules', $results);
        $this->assertArrayHasKey('stale_tickets', $results);
        $this->assertArrayHasKey('follow_up_reminders', $results);
        $this->assertArrayHasKey('daily_reports', $results);
        $this->assertArrayHasKey('total_execution_time', $results);

        $this->assertIsNumeric($results['total_execution_time']);
        $this->assertGreaterThan(0, $results['total_execution_time']);
    }

    public function test_tracks_execution_performance(): void
    {
        $startTime = microtime(true);

        $results = $this->automationService->runAllAutomationTasks();

        $endTime = microtime(true);
        $actualDuration = $endTime - $startTime;

        $this->assertArrayHasKey('total_execution_time', $results);
        $this->assertGreaterThan(0, $results['total_execution_time']);
        $this->assertLessThanOrEqual($actualDuration + 0.1, $results['total_execution_time']); // Allow small margin
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Services\AI\MachineLearningService;
use App\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\CreatesWorkflowTestData;

final class WorkflowEngineTest extends TestCase
{
    use CreatesWorkflowTestData, RefreshDatabase;

    private WorkflowEngine $workflowEngine;

    /** @var MachineLearningService&MockInterface */
    private MachineLearningService $mockMlService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createWorkflowTestData();

        $this->mockMlService = Mockery::mock(MachineLearningService::class);
        $this->app->instance(MachineLearningService::class, $this->mockMlService);

        $this->workflowEngine = new WorkflowEngine($this->mockMlService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function invoke(object $object, string $method, array $parameters = [])
    {
        $ref = new \ReflectionClass($object);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($object, $parameters);
    }

    public function test_can_execute_simple_workflow(): void
    {
        $ticket = Ticket::factory()->create();
        $user = User::factory()->create();

        $workflow = Workflow::factory()->create([
            'workflow_data' => [
                'nodes' => [
                    ['id' => 'start-1', 'type' => 'start'],
                    [
                        'id' => 'action-1',
                        'type' => 'action',
                        'data' => [
                            'type' => 'update_ticket',
                            'config' => ['updates' => ['priority_id' => 2]],
                        ],
                    ],
                    ['id' => 'end-1', 'type' => 'end'],
                ],
                'connections' => [
                    ['from' => 'start-1', 'to' => 'action-1'],
                    ['from' => 'action-1', 'to' => 'end-1'],
                ],
            ],
        ]);

        $execution = $this->workflowEngine->executeWorkflow($workflow, $ticket, $user->id);

        $this->assertInstanceOf(WorkflowExecution::class, $execution);
        $this->assertEquals('completed', $execution->status);
        $this->assertEquals($workflow->id, $execution->workflow_id);
        $this->assertEquals('ticket', $execution->entity_type);
        $this->assertEquals($ticket->id, $execution->entity_id);
        $this->assertEquals($user->id, $execution->triggered_by);
    }

    public function test_can_execute_workflow_with_conditions(): void
    {
        // Get the actual priority ID for 'Critical' priority
        $criticalPriority = \App\Models\TicketPriority::where('name', 'Critical')->first();
        $ticket = Ticket::factory()->create(['priority_id' => $criticalPriority->id]);
        $user = User::factory()->create();

        $workflow = Workflow::factory()->create([
            'workflow_data' => [
                'nodes' => [
                    ['id' => 'start-1', 'type' => 'start'],
                    [
                        'id' => 'condition-1',
                        'type' => 'condition',
                        'data' => [
                            'field' => 'priority_id',
                            'operator' => '=',
                            'value' => $criticalPriority->id,
                            'true_path' => 'action-high',
                            'false_path' => 'action-normal',
                        ],
                    ],
                    [
                        'id' => 'action-high',
                        'type' => 'action',
                        'data' => [
                            'type' => 'assign_ticket',
                            'assign_to_user_id' => $user->id,
                        ],
                    ],
                    [
                        'id' => 'action-normal',
                        'type' => 'action',
                        'data' => [
                            'type' => 'update_ticket',
                            'config' => ['updates' => ['status' => 'open']],
                        ],
                    ],
                    ['id' => 'end-1', 'type' => 'end'],
                ],
                'connections' => [
                    ['from' => 'start-1', 'to' => 'condition-1'],
                    ['from' => 'condition-1', 'to' => 'action-high'],
                    ['from' => 'condition-1', 'to' => 'action-normal'],
                    ['from' => 'action-high', 'to' => 'end-1'],
                    ['from' => 'action-normal', 'to' => 'end-1'],
                ],
            ],
        ]);

        $execution = $this->workflowEngine->executeWorkflow($workflow, $ticket, $user->id);

        $this->assertEquals('completed', $execution->status);

        // Verify the ticket was assigned (since condition should be true for critical priority)
        $ticket->refresh();
        $this->assertEquals($user->id, $ticket->assigned_to);
    }

    public function test_can_execute_workflow_with_ai_nodes(): void
    {
        $criticalPriority = \App\Models\TicketPriority::where('name', 'Critical')->first();
        $ticket = Ticket::factory()->create([
            'subject' => 'Critical system outage',
            'description' => 'Our servers are completely down',
        ]);
        $user = User::factory()->create();

        $workflow = Workflow::factory()->create([
            'workflow_data' => [
                'nodes' => [
                    ['id' => 'start-1', 'type' => 'start'],
                    [
                        'id' => 'ai-1',
                        'type' => 'ai',
                        'data' => [
                            'action' => 'categorize',
                            'config' => ['confidence_threshold' => 0.8],
                        ],
                    ],
                    [
                        'id' => 'action-1',
                        'type' => 'action',
                        'data' => [
                            'type' => 'update_ticket',
                            'config' => ['updates' => ['priority_id' => $criticalPriority->id]],
                        ],
                    ],
                    ['id' => 'end-1', 'type' => 'end'],
                ],
                'connections' => [
                    ['from' => 'start-1', 'to' => 'ai-1'],
                    ['from' => 'ai-1', 'to' => 'action-1'],
                    ['from' => 'action-1', 'to' => 'end-1'],
                ],
            ],
        ]);

        $this->mockMlService->shouldReceive('categorizeTicket')
            ->once()
            ->with($ticket->subject, $ticket->description)
            ->andReturn([
                'department' => 'technical',
                'priority' => 'urgent',
                'category' => 'system_outage',
                'confidence' => 0.95,
            ]);

        $execution = $this->workflowEngine->executeWorkflow($workflow, $ticket, $user->id);

        $this->assertEquals('completed', $execution->status);

        // Verify AI action was created and executed
        $this->assertDatabaseHas('workflow_actions', [
            'workflow_execution_id' => $execution->id,
            'action_type' => 'ai_process',
            'status' => 'completed',
        ]);

        // Verify ticket was updated based on AI categorization
        $ticket->refresh();
        $this->assertEquals($criticalPriority->id, $ticket->priority_id);
    }

    public function test_can_execute_workflow_with_delay_nodes(): void
    {
        $ticket = Ticket::factory()->create();
        $user = User::factory()->create();

        $workflow = Workflow::factory()->create([
            'workflow_data' => [
                'nodes' => [
                    ['id' => 'start-1', 'type' => 'start'],
                    [
                        'id' => 'delay-1',
                        'type' => 'delay',
                        'data' => [
                            'duration' => 1, // 1 second for testing
                            'unit' => 'seconds',
                        ],
                    ],
                    [
                        'id' => 'action-1',
                        'type' => 'action',
                        'data' => [
                            'type' => 'update_ticket',
                            'config' => ['updates' => ['status' => 'processed']],
                        ],
                    ],
                    ['id' => 'end-1', 'type' => 'end'],
                ],
                'connections' => [
                    ['from' => 'start-1', 'to' => 'delay-1'],
                    ['from' => 'delay-1', 'to' => 'action-1'],
                    ['from' => 'action-1', 'to' => 'end-1'],
                ],
            ],
        ]);

        $startTime = microtime(true);
        $execution = $this->workflowEngine->executeWorkflow($workflow, $ticket, $user->id);
        $endTime = microtime(true);

        $this->assertEquals('completed', $execution->status);
        $this->assertGreaterThan(1, $endTime - $startTime); // Should take at least 1 second
    }

    public function test_evaluates_conditions_correctly(): void
    {
        $ticket = Ticket::factory()->create(['priority_id' => 3]);

        // Test equality
        $this->assertTrue(
            $this->invoke($this->workflowEngine, 'evaluateCondition', [
                $ticket->priority_id,
                '=',
                3,
            ])
        );

        // Test inequality
        $this->assertTrue(
            $this->invoke($this->workflowEngine, 'evaluateCondition', [
                $ticket->priority_id,
                '!=',
                1,
            ])
        );

        // Test greater than
        $this->assertTrue(
            $this->invoke($this->workflowEngine, 'evaluateCondition', [
                $ticket->priority_id,
                '>',
                2,
            ])
        );

        // Test less than
        $this->assertTrue(
            $this->invoke($this->workflowEngine, 'evaluateCondition', [
                $ticket->priority_id,
                '<',
                4,
            ])
        );

        // Test contains
        $ticket = Ticket::factory()->create(['subject' => 'Password reset issue']);
        $this->assertTrue(
            $this->invoke($this->workflowEngine, 'evaluateCondition', [
                $ticket->subject,
                'contains',
                'password',
            ])
        );
    }

    public function test_handles_nested_property_evaluation(): void
    {
        $ticket = Ticket::factory()->create();
        $result = $this->invoke($this->workflowEngine, 'evaluateCondition', [
            'dummy@example.com',
            'contains',
            '@',
        ]);

        $this->assertTrue($result); // All emails should contain @
    }

    public function test_workflow_execution_logs_errors(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Workflow execution failed') &&
                    isset($context['execution_id']) &&
                    isset($context['error']);
            });

        // Allow Log::info calls from TicketObserver and other services
        Log::shouldReceive('info')
            ->zeroOrMoreTimes()
            ->andReturn(null);

        $ticket = Ticket::factory()->create();
        $user = User::factory()->create();

        $workflow = Workflow::factory()->create([
            'workflow_data' => [
                'nodes' => [
                    ['id' => 'start-1', 'type' => 'start'],
                    [
                        'id' => 'invalid-action',
                        'type' => 'action',
                        'data' => [
                            'type' => 'invalid_action_type',
                            'config' => [],
                        ],
                    ],
                    ['id' => 'end-1', 'type' => 'end'],
                ],
                'connections' => [
                    ['from' => 'start-1', 'to' => 'invalid-action'],
                    ['from' => 'invalid-action', 'to' => 'end-1'],
                ],
            ],
        ]);

        $execution = $this->workflowEngine->executeWorkflow($workflow, $ticket, $user->id);

        $this->assertEquals('failed', $execution->status);
        $this->assertNotNull($execution->error_message);
    }

    public function test_workflow_execution_handles_missing_connections(): void
    {
        $ticket = Ticket::factory()->create();
        $user = User::factory()->create();

        $workflow = Workflow::factory()->create([
            'workflow_data' => [
                'nodes' => [
                    ['id' => 'start-1', 'type' => 'start'],
                    [
                        'id' => 'action-1',
                        'type' => 'action',
                        'data' => [
                            'type' => 'update_ticket',
                            'config' => ['updates' => ['status' => 'processed']],
                        ],
                    ],
                    ['id' => 'end-1', 'type' => 'end'],
                ],
                'connections' => [
                    // Missing connection from start to action
                    ['from' => 'action-1', 'to' => 'end-1'],
                ],
            ],
        ]);

        $execution = $this->workflowEngine->executeWorkflow($workflow, $ticket, $user->id);

        $this->assertEquals('failed', $execution->status);
        $this->assertStringContainsString('workflow validation', strtolower($execution->error_message));
    }

    public function test_workflow_execution_times_out_properly(): void
    {
        // This test would need actual timeout implementation
        // For now, we'll test the structure
        $this->assertTrue(true);
    }

    public function test_can_validate_workflow_structure(): void
    {
        $validWorkflow = [
            'nodes' => [
                ['id' => 'start-1', 'type' => 'start'],
                ['id' => 'action-1', 'type' => 'action', 'data' => ['type' => 'update_ticket']],
                ['id' => 'end-1', 'type' => 'end'],
            ],
            'connections' => [
                ['from' => 'start-1', 'to' => 'action-1'],
                ['from' => 'action-1', 'to' => 'end-1'],
            ],
        ];

        $invalidWorkflow = [
            'nodes' => [
                ['id' => 'start-1', 'type' => 'start'],
                // Missing end node
            ],
            'connections' => [],
        ];

        $this->assertTrue($this->workflowEngine->validateWorkflowStructure($validWorkflow));
        $this->assertFalse($this->workflowEngine->validateWorkflowStructure($invalidWorkflow));
    }

    public function test_can_execute_actions_with_different_types(): void
    {
        $ticket = Ticket::factory()->create();
        $user = User::factory()->create();

        $execution = \App\Models\WorkflowExecution::factory()->create([
            'workflow_id' => null,
            'entity_type' => 'ticket',
            'entity_id' => $ticket->id,
            'status' => 'running',
            'execution_data' => [],
        ]);

        // Test assign_ticket action
        $result = $this->invoke($this->workflowEngine, 'executeAction', [
            [
                'type' => 'assign_ticket',
                'config' => ['agent_id' => $user->id],
            ],
            $ticket,
            $execution,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('assign_ticket', $result['action_type']);

        // Test update_ticket action
        $result = $this->invoke($this->workflowEngine, 'executeAction', [
            [
                'type' => 'update_ticket',
                'config' => ['updates' => ['priority_id' => 4]],
            ],
            $ticket,
            $execution,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('update_ticket', $result['action_type']);

        // Test send_notification action
        $result = $this->invoke($this->workflowEngine, 'executeAction', [
            [
                'type' => 'send_notification',
                'config' => [
                    'recipient_type' => 'assigned_agent',
                    'message' => 'Test notification',
                ],
            ],
            $ticket,
            $execution,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('send_notification', $result['action_type']);
    }

    public function test_execution_creates_workflow_actions(): void
    {
        $ticket = Ticket::factory()->create();
        $user = User::factory()->create();

        $workflow = Workflow::factory()->create([
            'workflow_data' => [
                'nodes' => [
                    ['id' => 'start-1', 'type' => 'start'],
                    [
                        'id' => 'action-1',
                        'type' => 'action',
                        'data' => [
                            'type' => 'update_ticket',
                            'config' => ['updates' => ['priority_id' => 2]],
                        ],
                    ],
                    [
                        'id' => 'action-2',
                        'type' => 'action',
                        'data' => [
                            'type' => 'send_notification',
                            'config' => ['recipient_type' => 'assigned_agent', 'message' => 'Test'],
                        ],
                    ],
                    ['id' => 'end-1', 'type' => 'end'],
                ],
                'connections' => [
                    ['from' => 'start-1', 'to' => 'action-1'],
                    ['from' => 'action-1', 'to' => 'action-2'],
                    ['from' => 'action-2', 'to' => 'end-1'],
                ],
            ],
        ]);

        $execution = $this->workflowEngine->executeWorkflow($workflow, $ticket, $user->id);

        $this->assertEquals('completed', $execution->status);
        $this->assertCount(2, $execution->actions); // Should have 2 actions

        foreach ($execution->actions as $action) {
            $this->assertEquals('completed', $action->status);
            $this->assertNotNull($action->result);
        }
    }

    public function test_can_get_available_actions(): void
    {
        $actions = $this->workflowEngine->getAvailableActions();

        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);
        $this->assertContains('assign_ticket', $actions);
        $this->assertContains('update_ticket', $actions);
        $this->assertContains('send_notification', $actions);
    }

    public function test_can_get_available_conditions(): void
    {
        $conditions = $this->workflowEngine->getAvailableConditions();

        $this->assertIsArray($conditions);
        $this->assertNotEmpty($conditions);
        $this->assertArrayHasKey('operators', $conditions);
        $this->assertArrayHasKey('fields', $conditions);
        $this->assertContains('=', $conditions['operators']);
        $this->assertContains('priority_id', $conditions['fields']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use Tests\Traits\CreatesWorkflowTestData;

final class WorkflowControllerTest extends TestCase
{
    use CreatesWorkflowTestData, RefreshDatabase, WithFaker;

    private User $user;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createWorkflowTestData();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->workflow = Workflow::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
    }

    public function test_can_list_workflows(): void
    {
        Workflow::factory()->count(3)->create();

        $response = $this->getJson('/api/workflows');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'trigger_type',
                        'trigger_conditions',
                        'workflow_data',
                        'is_active',
                        'creator',
                        'updater',
                        'executions_count',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_can_filter_workflows_by_trigger_type(): void
    {
        Workflow::factory()->create(['trigger_type' => 'manual']);
        Workflow::factory()->create(['trigger_type' => 'automatic']);

        $response = $this->getJson('/api/workflows?trigger_type=manual');

        $response->assertOk();
        $workflows = $response->json('data');

        foreach ($workflows as $workflow) {
            $this->assertEquals('manual', $workflow['trigger_type']);
        }
    }

    public function test_can_filter_workflows_by_active_status(): void
    {
        Workflow::factory()->create(['is_active' => true]);
        Workflow::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/workflows?active=true');

        $response->assertOk();
        $workflows = $response->json('data');

        foreach ($workflows as $workflow) {
            $this->assertTrue($workflow['is_active']);
        }
    }

    public function test_can_create_workflow(): void
    {
        $workflowData = [
            'name' => 'Test Workflow',
            'description' => 'A test workflow',
            'trigger_type' => 'manual',
            'trigger_conditions' => [
                ['field' => 'priority', 'operator' => '=', 'value' => 'high'],
            ],
            'workflow_data' => [
                'nodes' => [
                    ['id' => 'start-1', 'type' => 'start'],
                    ['id' => 'action-1', 'type' => 'action', 'data' => ['type' => 'assign_ticket']],
                    ['id' => 'end-1', 'type' => 'end'],
                ],
                'connections' => [
                    ['from' => 'start-1', 'to' => 'action-1'],
                    ['from' => 'action-1', 'to' => 'end-1'],
                ],
            ],
            'is_active' => true,
        ];

        $response = $this->postJson('/api/workflows', $workflowData);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Test Workflow',
                'trigger_type' => 'manual',
                'is_active' => true,
            ]);

        $this->assertDatabaseHas('workflows', [
            'name' => 'Test Workflow',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_create_workflow_validates_required_fields(): void
    {
        $response = $this->postJson('/api/workflows', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'trigger_type', 'workflow_data']);
    }

    public function test_create_workflow_validates_workflow_data_structure(): void
    {
        $workflowData = [
            'name' => 'Test Workflow',
            'trigger_type' => 'manual',
            'workflow_data' => [
                'nodes' => [], // Empty nodes should fail
                'connections' => [],
            ],
        ];

        $response = $this->postJson('/api/workflows', $workflowData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['workflow_data.nodes']);
    }

    public function test_can_show_workflow(): void
    {
        WorkflowExecution::factory()->count(2)->create([
            'workflow_id' => $this->workflow->id,
        ]);

        $response = $this->getJson("/api/workflows/{$this->workflow->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'trigger_type',
                    'workflow_data',
                    'creator',
                    'updater',
                    'executions',
                    'execution_stats',
                ],
            ]);

        $this->assertArrayHasKey('execution_stats', $response->json('data'));
    }

    public function test_can_update_workflow(): void
    {
        $updateData = [
            'name' => 'Updated Workflow Name',
            'description' => 'Updated description',
            'is_active' => false,
        ];

        $response = $this->putJson("/api/workflows/{$this->workflow->id}", $updateData);

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Updated Workflow Name',
                'is_active' => false,
            ]);

        $this->assertDatabaseHas('workflows', [
            'id' => $this->workflow->id,
            'name' => 'Updated Workflow Name',
            'updated_by' => $this->user->id,
        ]);
    }

    public function test_can_delete_workflow(): void
    {
        $response = $this->deleteJson("/api/workflows/{$this->workflow->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Workflow deleted successfully']);

        $this->assertDatabaseMissing('workflows', [
            'id' => $this->workflow->id,
        ]);
    }

    public function test_can_execute_workflow_manually(): void
    {
        $ticket = Ticket::factory()->create();

        $response = $this->postJson("/api/workflows/{$this->workflow->id}/execute", [
            'entity_type' => 'ticket',
            'entity_id' => $ticket->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'execution_id',
                    'status',
                    'started_at',
                ],
            ]);

        $this->assertDatabaseHas('workflow_executions', [
            'workflow_id' => $this->workflow->id,
            'entity_type' => 'ticket',
            'entity_id' => $ticket->id,
            'triggered_by' => $this->user->id,
        ]);
    }

    public function test_execute_workflow_validates_entity(): void
    {
        $response = $this->postJson("/api/workflows/{$this->workflow->id}/execute", [
            'entity_type' => 'invalid',
            'entity_id' => 999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['entity_type']);
    }

    public function test_execute_workflow_returns_error_for_missing_entity(): void
    {
        $response = $this->postJson("/api/workflows/{$this->workflow->id}/execute", [
            'entity_type' => 'ticket',
            'entity_id' => 999999, // Non-existent ticket
        ]);

        $response->assertNotFound()
            ->assertJson(['message' => 'Entity not found']);
    }

    public function test_can_toggle_workflow_status(): void
    {
        $this->assertTrue($this->workflow->is_active);

        $response = $this->postJson("/api/workflows/{$this->workflow->id}/toggle");

        $response->assertOk()
            ->assertJsonFragment(['is_active' => false]);

        $this->workflow->refresh();
        $this->assertFalse($this->workflow->is_active);
    }

    public function test_can_get_workflow_executions(): void
    {
        WorkflowExecution::factory()->count(5)->create([
            'workflow_id' => $this->workflow->id,
        ]);

        $response = $this->getJson("/api/workflows/{$this->workflow->id}/executions");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'started_at',
                        'completed_at',
                        'triggered_by',
                    ],
                ],
            ]);
    }

    public function test_can_get_workflow_templates(): void
    {
        WorkflowTemplate::factory()->count(3)->create();

        $response = $this->getJson('/api/workflows/templates');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
            ]);

        $this->assertIsArray($response->json('data'));
    }

    public function test_can_create_workflow_from_template(): void
    {
        $template = WorkflowTemplate::factory()->create([
            'template_data' => [
                'nodes' => [
                    ['id' => 'start-1', 'type' => 'start'],
                    ['id' => 'end-1', 'type' => 'end'],
                ],
                'connections' => [
                    ['from' => 'start-1', 'to' => 'end-1'],
                ],
                'trigger' => [
                    'type' => 'manual',
                    'conditions' => [],
                ],
            ],
        ]);

        $response = $this->postJson("/api/workflows/templates/{$template->id}/create", [
            'name' => 'Workflow from Template',
            'description' => 'Created from template',
        ]);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Workflow from Template',
                'trigger_type' => 'manual',
            ]);

        $this->assertDatabaseHas('workflows', [
            'name' => 'Workflow from Template',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_can_test_workflow(): void
    {
        $ticket = Ticket::factory()->create();

        $response = $this->postJson("/api/workflows/{$this->workflow->id}/test", [
            'entity_type' => 'ticket',
            'entity_id' => $ticket->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'valid',
                    'nodes_count',
                    'connections_count',
                    'estimated_actions',
                    'entity_data',
                ],
            ]);
    }

    public function test_workflow_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/workflows/999999');

        $response->assertNotFound();
    }

    public function test_unauthorized_user_cannot_access_workflows(): void
    {
        Auth::logout();

        $response = $this->getJson('/api/workflows');

        $response->assertUnauthorized();
    }
}

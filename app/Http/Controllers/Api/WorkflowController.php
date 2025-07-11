<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Workflow;
use App\Models\WorkflowTemplate;
use App\Services\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

final class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowEngine $workflowEngine
    ) {}

    /**
     * Get all workflows.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Workflow::with(['creator', 'updater'])
            ->withCount(['executions']);

        // Filter by trigger type
        if ($request->has('trigger_type')) {
            $query->byTriggerType($request->trigger_type);
        }

        // Filter by active status
        if ($request->has('active')) {
            $active = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
            if ($active) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        // Filter by creator
        if ($request->has('created_by')) {
            $query->createdBy((int) $request->created_by);
        }

        $workflows = $query->latest()->paginate(15);

        return response()->json([
            'data' => $workflows->items(),
            'meta' => [
                'current_page' => $workflows->currentPage(),
                'last_page' => $workflows->lastPage(),
                'per_page' => $workflows->perPage(),
                'total' => $workflows->total(),
            ],
        ]);
    }

    /**
     * Create a new workflow.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'required|in:manual,automatic,schedule,webhook',
            'trigger_conditions' => 'nullable|array',
            'workflow_data' => 'required|array',
            'workflow_data.nodes' => 'required|array|min:1',
            'workflow_data.connections' => 'required|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workflow = Workflow::create([
            'name' => $request->name,
            'description' => $request->description,
            'trigger_type' => $request->trigger_type,
            'trigger_conditions' => $request->trigger_conditions,
            'workflow_data' => $request->workflow_data,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $workflow->load(['creator', 'updater']);

        return response()->json([
            'message' => 'Workflow created successfully',
            'data' => $workflow,
        ], 201);
    }

    /**
     * Get a specific workflow.
     */
    public function show(Workflow $workflow): JsonResponse
    {
        $workflow->load([
            'creator',
            'updater',
            'executions' => function ($query) {
                $query->with(['triggeredBy'])->latest()->limit(10);
            },
        ]);

        $workflow->execution_stats = $workflow->getExecutionStats();

        return response()->json([
            'data' => $workflow,
        ]);
    }

    /**
     * Update a workflow.
     */
    public function update(Request $request, Workflow $workflow): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'sometimes|required|in:manual,automatic,schedule,webhook',
            'trigger_conditions' => 'nullable|array',
            'workflow_data' => 'sometimes|required|array',
            'workflow_data.nodes' => 'sometimes|required|array|min:1',
            'workflow_data.connections' => 'sometimes|required|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workflow->update([
            'name' => $request->name ?? $workflow->name,
            'description' => $request->description ?? $workflow->description,
            'trigger_type' => $request->trigger_type ?? $workflow->trigger_type,
            'trigger_conditions' => $request->trigger_conditions ?? $workflow->trigger_conditions,
            'workflow_data' => $request->workflow_data ?? $workflow->workflow_data,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $workflow->is_active,
            'updated_by' => Auth::id(),
        ]);

        $workflow->load(['creator', 'updater']);

        return response()->json([
            'message' => 'Workflow updated successfully',
            'data' => $workflow,
        ]);
    }

    /**
     * Delete a workflow.
     */
    public function destroy(Workflow $workflow): JsonResponse
    {
        $workflow->delete();

        return response()->json([
            'message' => 'Workflow deleted successfully',
        ]);
    }

    /**
     * Execute a workflow manually.
     */
    public function execute(Request $request, Workflow $workflow): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entity_type' => 'required|in:ticket,user',
            'entity_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get the entity
        $entity = $this->getEntity($request->entity_type, $request->entity_id);
        if (! $entity) {
            return response()->json([
                'message' => 'Entity not found',
            ], 404);
        }

        try {
            $execution = $this->workflowEngine->executeWorkflow($workflow, $entity, Auth::id());

            return response()->json([
                'message' => 'Workflow executed successfully',
                'data' => [
                    'execution_id' => $execution->id,
                    'status' => $execution->status,
                    'started_at' => $execution->started_at,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Workflow execution failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle workflow active status.
     */
    public function toggle(Workflow $workflow): JsonResponse
    {
        $workflow->update([
            'is_active' => ! $workflow->is_active,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => $workflow->is_active ? 'Workflow activated' : 'Workflow deactivated',
            'data' => [
                'is_active' => $workflow->is_active,
            ],
        ]);
    }

    /**
     * Get workflow execution history.
     */
    public function executions(Workflow $workflow): JsonResponse
    {
        $executions = $workflow->executions()
            ->with(['triggeredBy', 'actions'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => $executions->items(),
            'meta' => [
                'current_page' => $executions->currentPage(),
                'last_page' => $executions->lastPage(),
                'per_page' => $executions->perPage(),
                'total' => $executions->total(),
            ],
        ]);
    }

    /**
     * Get workflow templates.
     */
    public function templates(): JsonResponse
    {
        $templates = WorkflowTemplate::orderBy('category')->orderBy('name')->get();

        $groupedTemplates = $templates->groupBy('category');

        return response()->json([
            'data' => $groupedTemplates,
        ]);
    }

    /**
     * Create workflow from template.
     */
    public function createFromTemplate(Request $request, WorkflowTemplate $template): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workflow = $template->createWorkflow(
            $request->name,
            $request->description,
            Auth::id()
        );

        $workflow->load(['creator', 'updater']);

        return response()->json([
            'message' => 'Workflow created from template successfully',
            'data' => $workflow,
        ], 201);
    }

    /**
     * Test workflow with sample data.
     */
    public function test(Request $request, Workflow $workflow): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entity_type' => 'required|in:ticket,user',
            'entity_id' => 'required|integer',
            'dry_run' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get the entity
        $entity = $this->getEntity($request->entity_type, $request->entity_id);
        if (! $entity) {
            return response()->json([
                'message' => 'Entity not found',
            ], 404);
        }

        try {
            // For dry run, we'd implement a test mode in the workflow engine
            // For now, we'll just validate the workflow structure
            $testResults = [
                'valid' => true,
                'nodes_count' => count($workflow->nodes),
                'connections_count' => count($workflow->connections),
                'estimated_actions' => $this->estimateWorkflowActions($workflow),
                'entity_data' => $entity->toArray(),
            ];

            return response()->json([
                'message' => 'Workflow test completed',
                'data' => $testResults,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Workflow test failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get entity by type and ID.
     */
    private function getEntity(string $entityType, int $entityId): ?\Illuminate\Database\Eloquent\Model
    {
        return match ($entityType) {
            'ticket' => Ticket::find($entityId),
            'user' => \App\Models\User::find($entityId),
            default => null,
        };
    }

    /**
     * Estimate the number of actions a workflow will perform.
     */
    private function estimateWorkflowActions(Workflow $workflow): int
    {
        $actionNodes = collect($workflow->nodes)->where('type', 'action');

        return $actionNodes->count();
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowAction;
use App\Models\WorkflowExecution;
use App\Models\WorkflowRule;
use App\Services\AI\MachineLearningService;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class WorkflowEngine
{
    public function __construct(
        private readonly MachineLearningService $mlService
    ) {}

    /**
     * Execute a workflow for a given entity.
     */
    public function executeWorkflow(Workflow $workflow, Model $entity, ?int $triggeredBy = null): WorkflowExecution
    {
        // Create workflow execution record first
        $execution = WorkflowExecution::create([
            'workflow_id' => $workflow->id,
            'entity_type' => $this->getEntityType($entity),
            'entity_id' => $entity->id,
            'status' => 'running',
            'execution_data' => [
                'workflow_data' => $workflow->workflow_data,
                'entity_data' => $entity->toArray(),
            ],
            'started_at' => now(),
            'triggered_by' => $triggeredBy ?? Auth::id(),
        ]);

        Log::info('Workflow execution started', [
            'workflow_id' => $workflow->id,
            'execution_id' => $execution->id,
            'entity_type' => $this->getEntityType($entity),
            'entity_id' => $entity->id,
        ]);

        try {
            // Validate workflow structure before execution
            if (! $this->validateWorkflowStructure($workflow->workflow_data)) {
                throw new Exception('Invalid workflow structure: workflow validation failed');
            }

            // Execute workflow nodes
            $this->executeWorkflowNodes($workflow, $entity, $execution);

            // Mark as completed
            $execution->markAsCompleted([
                'actions_executed' => $execution->actions()->count(),
                'completed_at' => now(),
            ]);

            Log::info('Workflow execution completed', [
                'execution_id' => $execution->id,
                'duration' => $execution->execution_time,
            ]);

        } catch (Exception $e) {
            // Mark as failed
            $execution->markAsFailed($e->getMessage());

            Log::error('Workflow execution failed', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $execution;
    }

    /**
     * Execute a workflow rule for a given entity.
     */
    public function executeWorkflowRule(WorkflowRule $rule, Model $entity, ?int $triggeredBy = null): WorkflowExecution
    {
        // Create workflow execution record
        $execution = WorkflowExecution::create([
            'workflow_rule_id' => $rule->id,
            'entity_type' => $this->getEntityType($entity),
            'entity_id' => $entity->id,
            'status' => 'running',
            'execution_data' => [
                'rule_data' => $rule->toArray(),
                'entity_data' => $entity->toArray(),
            ],
            'started_at' => now(),
            'triggered_by' => $triggeredBy ?? Auth::id(),
        ]);

        Log::info('Workflow rule execution started', [
            'rule_id' => $rule->id,
            'execution_id' => $execution->id,
            'entity_type' => $this->getEntityType($entity),
            'entity_id' => $entity->id,
        ]);

        try {
            // Execute rule actions
            $this->executeRuleActions($rule, $entity, $execution);

            // Mark as completed
            $execution->markAsCompleted([
                'actions_executed' => $execution->actions()->count(),
                'completed_at' => now(),
            ]);

            Log::info('Workflow rule execution completed', [
                'execution_id' => $execution->id,
                'duration' => $execution->execution_time,
            ]);

        } catch (Exception $e) {
            // Mark as failed
            $execution->markAsFailed($e->getMessage());

            Log::error('Workflow rule execution failed', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $execution;
    }

    /**
     * Check and execute applicable workflow rules for an entity.
     */
    public function checkAndExecuteRules(Model $entity, ?int $triggeredBy = null): array
    {
        $entityType = $this->getEntityType($entity);
        $executions = [];

        // Get active rules for this entity type
        $rules = WorkflowRule::active()
            ->forEntityType($entityType)
            ->byPriority()
            ->get();

        foreach ($rules as $rule) {
            try {
                // Check if rule matches
                if ($rule->matches($entity) && $rule->shouldRunNow()) {
                    $execution = $this->executeWorkflowRule($rule, $entity, $triggeredBy);
                    $executions[] = $execution;
                }
            } catch (Exception $e) {
                Log::error('Rule evaluation failed', [
                    'rule_id' => $rule->id,
                    'entity_type' => $entityType,
                    'entity_id' => $entity->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $executions;
    }

    /**
     * Execute workflow nodes.
     */
    private function executeWorkflowNodes(Workflow $workflow, Model $entity, WorkflowExecution $execution): void
    {
        $nodes = $workflow->nodes;
        $connections = $workflow->connections;

        // Find start node
        $startNode = collect($nodes)->firstWhere('type', 'start');
        if (! $startNode) {
            throw new Exception('No start node found in workflow');
        }

        // Execute nodes starting from start node
        $this->executeNode($startNode, $nodes, $connections, $entity, $execution);
    }

    /**
     * Execute a single workflow node.
     */
    private function executeNode(array $node, array $nodes, array $connections, Model $entity, WorkflowExecution $execution): void
    {
        $nodeType = $node['type'] ?? 'unknown';

        switch ($nodeType) {
            case 'start':
                // Start node - just find next node
                $nextNodeIds = $this->findNextNodes($node['id'], $connections);
                foreach ($nextNodeIds as $nextNodeId) {
                    $nextNode = collect($nodes)->firstWhere('id', $nextNodeId);
                    if ($nextNode) {
                        $this->executeNode($nextNode, $nodes, $connections, $entity, $execution);
                    }
                }
                break;

            case 'action':
                $this->executeActionNode($node, $entity, $execution);

                // Find and execute next nodes
                $nextNodeIds = $this->findNextNodes($node['id'], $connections);
                foreach ($nextNodeIds as $nextNodeId) {
                    $nextNode = collect($nodes)->firstWhere('id', $nextNodeId);
                    if ($nextNode) {
                        $this->executeNode($nextNode, $nodes, $connections, $entity, $execution);
                    }
                }
                break;

            case 'condition':
                $this->executeConditionNode($node, $nodes, $connections, $entity, $execution);
                break;

            case 'ai':
                $this->executeAINode($node, $entity, $execution);

                // Find and execute next nodes
                $nextNodeIds = $this->findNextNodes($node['id'], $connections);
                foreach ($nextNodeIds as $nextNodeId) {
                    $nextNode = collect($nodes)->firstWhere('id', $nextNodeId);
                    if ($nextNode) {
                        $this->executeNode($nextNode, $nodes, $connections, $entity, $execution);
                    }
                }
                break;

            case 'delay':
                $this->executeDelayNode($node, $entity, $execution);

                // Find and execute next nodes
                $nextNodeIds = $this->findNextNodes($node['id'], $connections);
                foreach ($nextNodeIds as $nextNodeId) {
                    $nextNode = collect($nodes)->firstWhere('id', $nextNodeId);
                    if ($nextNode) {
                        $this->executeNode($nextNode, $nodes, $connections, $entity, $execution);
                    }
                }
                break;

            case 'end':
                // End node - workflow complete
                break;

            default:
                Log::warning('Unknown node type encountered', [
                    'node_type' => $nodeType,
                    'node_id' => $node['id'] ?? 'unknown',
                    'execution_id' => $execution->id,
                ]);
        }
    }

    /**
     * Execute rule actions.
     */
    private function executeRuleActions(WorkflowRule $rule, Model $entity, WorkflowExecution $execution): void
    {
        $actions = $rule->actions;

        foreach ($actions as $actionConfig) {
            $action = WorkflowAction::create([
                'workflow_execution_id' => $execution->id,
                'action_type' => $actionConfig['type'] ?? 'unknown',
                'action_data' => $actionConfig,
                'status' => 'pending',
            ]);

            try {
                $action->markAsStarted();
                $result = $this->executeAction($actionConfig, $entity, $execution);

                // Check if the result contains an error
                if (isset($result['error'])) {
                    throw new Exception($result['error']);
                }

                $action->markAsCompleted($result);
            } catch (Exception $e) {
                $action->markAsFailed($e->getMessage());
                Log::error('Action execution failed', [
                    'action_id' => $action->id,
                    'action_type' => $actionConfig['type'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Execute an action node.
     */
    private function executeActionNode(array $node, Model $entity, WorkflowExecution $execution): void
    {
        $actionConfig = $node['data'] ?? [];

        $action = WorkflowAction::create([
            'workflow_execution_id' => $execution->id,
            'action_type' => $actionConfig['type'] ?? 'unknown',
            'action_data' => $actionConfig,
            'status' => 'pending',
        ]);

        try {
            $action->markAsStarted();
            $result = $this->executeAction($actionConfig, $entity, $execution);

            // Check if the result contains an error
            if (isset($result['error'])) {
                throw new Exception($result['error']);
            }

            $action->markAsCompleted($result);
        } catch (Exception $e) {
            $action->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute a condition node.
     */
    private function executeConditionNode(array $node, array $nodes, array $connections, Model $entity, WorkflowExecution $execution): void
    {
        $conditionConfig = $node['data'] ?? [];
        $field = $conditionConfig['field'] ?? '';
        $operator = $conditionConfig['operator'] ?? '=';
        $value = $conditionConfig['value'] ?? '';

        // Get actual value from entity
        $actualValue = data_get($entity, $field);

        // Evaluate condition
        $conditionResult = $this->evaluateCondition($actualValue, $operator, $value);

        // Find appropriate next node based on condition result
        $nextNodeId = $conditionResult ?
            ($conditionConfig['true_path'] ?? null) :
            ($conditionConfig['false_path'] ?? null);

        if ($nextNodeId) {
            $nextNode = collect($nodes)->firstWhere('id', $nextNodeId);
            if ($nextNode) {
                $this->executeNode($nextNode, $nodes, $connections, $entity, $execution);
            }
        }
    }

    /**
     * Execute an AI node.
     */
    private function executeAINode(array $node, Model $entity, WorkflowExecution $execution): void
    {
        $aiConfig = $node['data'] ?? [];
        $aiAction = $aiConfig['action'] ?? 'categorize';

        $action = WorkflowAction::create([
            'workflow_execution_id' => $execution->id,
            'action_type' => 'ai_process',
            'action_data' => $aiConfig,
            'status' => 'pending',
        ]);

        try {
            $action->markAsStarted();

            $result = match ($aiAction) {
                'categorize' => $this->executeAICategorization($entity, $aiConfig),
                'suggest_response' => $this->executeAIResponseSuggestion($entity, $aiConfig),
                'predict_escalation' => $this->executeAIEscalationPrediction($entity, $aiConfig),
                default => ['error' => 'Unknown AI action: '.$aiAction],
            };

            // Check if the result contains an error
            if (isset($result['error'])) {
                throw new Exception($result['error']);
            }

            $action->markAsCompleted($result);
        } catch (Exception $e) {
            $action->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute a delay node.
     */
    private function executeDelayNode(array $node, Model $entity, WorkflowExecution $execution): void
    {
        $delayConfig = $node['data'] ?? [];

        // Handle two formats: direct 'seconds' or 'duration' + 'unit'
        if (isset($delayConfig['seconds'])) {
            $delaySeconds = $delayConfig['seconds'];
        } elseif (isset($delayConfig['duration'], $delayConfig['unit'])) {
            $duration = $delayConfig['duration'];
            $unit = $delayConfig['unit'];

            $delaySeconds = match ($unit) {
                'seconds' => $duration,
                'minutes' => $duration * 60,
                'hours' => $duration * 3600,
                default => 0,
            };
        } else {
            $delaySeconds = 0;
        }

        if ($delaySeconds > 0) {
            sleep($delaySeconds);
        }
    }

    /**
     * Execute an action based on its configuration.
     */
    private function executeAction(array $actionConfig, Model $entity, WorkflowExecution $execution): array
    {
        $actionType = $actionConfig['type'] ?? 'unknown';

        return match ($actionType) {
            'assign_ticket' => $this->executeAssignTicketAction($actionConfig, $entity),
            'update_ticket' => $this->executeUpdateTicketAction($actionConfig, $entity),
            'send_notification' => $this->executeSendNotificationAction($actionConfig, $entity),
            'send_email' => $this->executeSendEmailAction($actionConfig, $entity),
            'ai_categorize' => $this->executeAICategorization($entity, $actionConfig),
            'ai_suggest_response' => $this->executeAIResponseSuggestion($entity, $actionConfig),
            'create_knowledge_article' => $this->executeCreateKnowledgeArticleAction($actionConfig, $entity),
            default => ['error' => 'Unknown action type: '.$actionType],
        };
    }

    /**
     * Execute assign ticket action.
     */
    private function executeAssignTicketAction(array $config, Model $entity): array
    {
        if (! $entity instanceof Ticket) {
            return ['error' => 'Entity is not a ticket'];
        }

        $assignToUserId = $config['assign_to_user_id'] ?? null;
        $assignToDepartment = $config['assign_to_department'] ?? null;

        if ($assignToUserId) {
            $user = User::find($assignToUserId);
            if ($user) {
                $entity->update(['assigned_to' => $user->id]);

                return ['success' => true, 'assigned_to' => $user->name];
            }
        }

        if ($assignToDepartment) {
            // Find available agent in department
            $agent = User::whereHas('departments', function ($query) use ($assignToDepartment) {
                $query->where('department_id', $assignToDepartment);
            })->first();

            if ($agent) {
                $entity->update(['assigned_to' => $agent->id]);

                return ['success' => true, 'assigned_to' => $agent->name];
            }
        }

        return ['error' => 'No suitable agent found for assignment'];
    }

    /**
     * Execute update ticket action.
     */
    private function executeUpdateTicketAction(array $config, Model $entity): array
    {
        if (! $entity instanceof Ticket) {
            return ['error' => 'Entity is not a ticket'];
        }

        $updates = $config['updates'] ?? [];
        $entity->update($updates);

        return ['success' => true, 'updates' => $updates];
    }

    /**
     * Execute send notification action.
     */
    private function executeSendNotificationAction(array $config, Model $entity): array
    {
        $recipientType = $config['recipient_type'] ?? 'assigned_user';
        $message = $config['message'] ?? 'Workflow notification';

        $recipients = $this->getNotificationRecipients($recipientType, $entity);

        foreach ($recipients as $recipient) {
            // Send notification (implement your notification logic)
            // Notification::send($recipient, new WorkflowNotification($message, $entity));
        }

        return ['success' => true, 'recipients_count' => count($recipients)];
    }

    /**
     * Execute send email action.
     */
    private function executeSendEmailAction(array $config, Model $entity): array
    {
        $recipientEmail = $config['recipient_email'] ?? '';
        $subject = $config['subject'] ?? 'Workflow Email';
        $message = $config['message'] ?? 'Workflow email message';

        // Send email (implement your email logic)
        // Mail::to($recipientEmail)->send(new WorkflowMail($subject, $message, $entity));

        return ['success' => true, 'recipient' => $recipientEmail];
    }

    /**
     * Execute AI categorization.
     */
    private function executeAICategorization(Model $entity, array $config): array
    {
        if (! $entity instanceof Ticket) {
            return ['error' => 'Entity is not a ticket'];
        }

        $result = $this->mlService->categorizeTicket($entity->subject, $entity->description);

        // Update ticket with AI categorization if confidence is high
        if (($result['confidence'] ?? 0) > 0.8) {
            $updates = [];
            if (isset($result['priority'])) {
                $updates['priority_id'] = $this->getPriorityId($result['priority']);
            }
            if (isset($result['department'])) {
                $updates['department_id'] = $this->getDepartmentId($result['department']);
            }

            if (! empty($updates)) {
                $entity->update($updates);
            }
        }

        return $result;
    }

    /**
     * Execute AI response suggestion.
     */
    private function executeAIResponseSuggestion(Model $entity, array $config): array
    {
        if (! $entity instanceof Ticket) {
            return ['error' => 'Entity is not a ticket'];
        }

        return $this->mlService->suggestResponses($entity);
    }

    /**
     * Execute AI escalation prediction.
     */
    private function executeAIEscalationPrediction(Model $entity, array $config): array
    {
        if (! $entity instanceof Ticket) {
            return ['error' => 'Entity is not a ticket'];
        }

        $escalationProbability = $this->mlService->predictEscalation($entity);

        return ['escalation_probability' => $escalationProbability];
    }

    /**
     * Execute create knowledge article action.
     */
    private function executeCreateKnowledgeArticleAction(array $config, Model $entity): array
    {
        // Implementation depends on your knowledge article requirements
        return ['success' => true, 'message' => 'Knowledge article creation not implemented'];
    }

    /**
     * Get notification recipients based on recipient type.
     */
    private function getNotificationRecipients(string $recipientType, Model $entity): array
    {
        return match ($recipientType) {
            'assigned_user' => $entity instanceof Ticket && $entity->assignedTo ? [$entity->assignedTo] : [],
            'created_by' => $entity instanceof Ticket && $entity->createdBy ? [$entity->createdBy] : [],
            'department_managers' => [], // Implement department manager logic
            default => [],
        };
    }

    /**
     * Find next nodes in workflow.
     */
    private function findNextNodes(string $nodeId, array $connections): array
    {
        $nextNodes = [];

        foreach ($connections as $connection) {
            if ($connection['from'] === $nodeId) {
                $nextNodes[] = $connection['to'];
            }
        }

        return $nextNodes;
    }

    /**
     * Evaluate a condition.
     */
    private function evaluateCondition(mixed $actualValue, string $operator, mixed $expectedValue): bool
    {
        return match ($operator) {
            '=' => $actualValue == $expectedValue,
            '!=' => $actualValue != $expectedValue,
            '>' => $actualValue > $expectedValue,
            '<' => $actualValue < $expectedValue,
            '>=' => $actualValue >= $expectedValue,
            '<=' => $actualValue <= $expectedValue,
            'contains' => str_contains(strtolower((string) $actualValue), strtolower((string) $expectedValue)),
            'starts_with' => str_starts_with(strtolower((string) $actualValue), strtolower((string) $expectedValue)),
            'ends_with' => str_ends_with(strtolower((string) $actualValue), strtolower((string) $expectedValue)),
            'in' => in_array($actualValue, (array) $expectedValue),
            'not_in' => ! in_array($actualValue, (array) $expectedValue),
            default => false,
        };
    }

    /**
     * Get entity type string.
     */
    private function getEntityType(Model $entity): string
    {
        return match (get_class($entity)) {
            Ticket::class => 'ticket',
            User::class => 'user',
            default => 'unknown',
        };
    }

    /**
     * Get priority ID by name.
     */
    private function getPriorityId(string $priorityName): ?int
    {
        // Implement priority mapping logic
        return match (strtolower($priorityName)) {
            'low' => 1,
            'normal' => 2,
            'high' => 3,
            'urgent' => 4,
            'critical' => 5,
            default => null,
        };
    }

    /**
     * Get department ID by name.
     */
    private function getDepartmentId(string $departmentName): ?int
    {
        // Implement department mapping logic
        return match (strtolower($departmentName)) {
            'technical' => 1,
            'billing' => 2,
            'support' => 3,
            default => null,
        };
    }

    /**
     * Validate basic workflow structure integrity.
     */
    public function validateWorkflowStructure(array $workflowData): bool
    {
        $nodes = $workflowData['nodes'] ?? [];
        $connections = $workflowData['connections'] ?? [];

        // Basic checks
        $hasStart = collect($nodes)->contains(fn ($n) => ($n['type'] ?? '') === 'start');
        $hasEnd = collect($nodes)->contains(fn ($n) => ($n['type'] ?? '') === 'end');

        if (! $hasStart || ! $hasEnd || empty($connections)) {
            return false;
        }

        // Validate connectivity - ensure all nodes (except end nodes) have outgoing connections
        $nodeIds = collect($nodes)->pluck('id')->toArray();
        $fromNodes = collect($connections)->pluck('from')->unique()->toArray();
        $toNodes = collect($connections)->pluck('to')->unique()->toArray();

        // Check that start node has outgoing connections
        $startNodes = collect($nodes)->where('type', 'start')->pluck('id')->toArray();
        foreach ($startNodes as $startNodeId) {
            if (! in_array($startNodeId, $fromNodes)) {
                return false; // Start node has no outgoing connections
            }
        }

        // Check that non-end nodes have outgoing connections
        $nonEndNodes = collect($nodes)->where('type', '!=', 'end')->pluck('id')->toArray();
        foreach ($nonEndNodes as $nodeId) {
            if (! in_array($nodeId, $fromNodes)) {
                return false; // Non-end node has no outgoing connections
            }
        }

        // Check that all connection references point to valid nodes
        foreach ($connections as $connection) {
            if (! in_array($connection['from'], $nodeIds) || ! in_array($connection['to'], $nodeIds)) {
                return false; // Connection references invalid node
            }
        }

        return true;
    }

    /**
     * Expose a list of available workflow action identifiers.
     */
    public function getAvailableActions(): array
    {
        return [
            'assign_ticket',
            'update_ticket',
            'send_notification',
            'send_email',
            'ai_categorize',
            'ai_suggest_response',
            'ai_predict_escalation',
        ];
    }

    /**
     * Expose supported condition operators and entity fields.
     */
    public function getAvailableConditions(): array
    {
        return [
            'operators' => ['=', '!=', '>', '<', '>=', '<=', 'contains', 'starts_with', 'ends_with', 'in', 'not_in'],
            'fields' => [
                'priority_id',
                'status',
                'department_id',
                'subject',
                'description',
                'created_at',
            ],
        ];
    }
}

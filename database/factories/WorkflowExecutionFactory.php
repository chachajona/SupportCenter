<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkflowExecution>
 */
final class WorkflowExecutionFactory extends Factory
{
    protected $model = WorkflowExecution::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['running', 'completed', 'failed', 'cancelled']);
        $startedAt = $this->faker->dateTimeBetween('-1 week', 'now');
        $completedAt = $status === 'running' ? null : $this->faker->dateTimeBetween($startedAt, 'now');

        return [
            'workflow_id' => Workflow::factory(),
            'workflow_rule_id' => null,
            'entity_type' => $this->faker->randomElement(['ticket', 'user']),
            'entity_id' => $this->faker->numberBetween(1, 1000),
            'status' => $status,
            'execution_data' => $this->generateExecutionData(),
            'execution_result' => $status === 'completed' ? $this->generateExecutionResult() : null,
            'error_message' => $status === 'failed' ? $this->faker->sentence() : null,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'triggered_by' => User::factory(),
        ];
    }

    /**
     * Generate execution data.
     *
     * @return array<string, mixed>
     */
    private function generateExecutionData(): array
    {
        return [
            'workflow_data' => [
                'nodes' => [
                    ['id' => 'start-1', 'type' => 'start'],
                    ['id' => 'action-1', 'type' => 'action'],
                    ['id' => 'end-1', 'type' => 'end'],
                ],
                'connections' => [
                    ['from' => 'start-1', 'to' => 'action-1'],
                    ['from' => 'action-1', 'to' => 'end-1'],
                ],
            ],
            'entity_data' => [
                'id' => $this->faker->numberBetween(1, 1000),
                'subject' => $this->faker->sentence(),
                'status' => 'open',
                'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'urgent']),
            ],
            'context' => [
                'trigger_source' => $this->faker->randomElement(['manual', 'automatic', 'scheduled']),
                'timestamp' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Generate execution result data.
     *
     * @return array<string, mixed>
     */
    private function generateExecutionResult(): array
    {
        return [
            'actions_executed' => $this->faker->numberBetween(1, 5),
            'completed_at' => now()->toISOString(),
            'summary' => [
                'total_actions' => $this->faker->numberBetween(1, 5),
                'successful_actions' => $this->faker->numberBetween(1, 5),
                'failed_actions' => 0,
                'execution_time_seconds' => $this->faker->numberBetween(1, 30),
            ],
            'changes_made' => [
                'ticket_updated' => $this->faker->boolean(),
                'notifications_sent' => $this->faker->numberBetween(0, 3),
                'assignments_made' => $this->faker->boolean(),
            ],
        ];
    }

    /**
     * Create a running execution.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'completed_at' => null,
            'execution_result' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Create a completed execution.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => $this->faker->dateTimeBetween($attributes['started_at'] ?? '-1 hour', 'now'),
            'execution_result' => $this->generateExecutionResult(),
            'error_message' => null,
        ]);
    }

    /**
     * Create a failed execution.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'completed_at' => $this->faker->dateTimeBetween($attributes['started_at'] ?? '-1 hour', 'now'),
            'execution_result' => null,
            'error_message' => $this->faker->randomElement([
                'Action execution failed: Invalid configuration',
                'Network timeout during API call',
                'Entity not found during execution',
                'Permission denied for action',
                'Workflow validation failed',
            ]),
        ]);
    }

    /**
     * Create a cancelled execution.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'completed_at' => $this->faker->dateTimeBetween($attributes['started_at'] ?? '-1 hour', 'now'),
            'execution_result' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Create an execution for a specific workflow.
     */
    public function forWorkflow(Workflow $workflow): static
    {
        return $this->state(fn (array $attributes) => [
            'workflow_id' => $workflow->id,
            'workflow_rule_id' => null,
        ]);
    }

    /**
     * Create an execution for a workflow rule.
     */
    public function forWorkflowRule(WorkflowRule $rule): static
    {
        return $this->state(fn (array $attributes) => [
            'workflow_id' => null,
            'workflow_rule_id' => $rule->id,
        ]);
    }

    /**
     * Create an execution for a ticket entity.
     */
    public function forTicket(int $ticketId): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => 'ticket',
            'entity_id' => $ticketId,
        ]);
    }

    /**
     * Create an execution for a user entity.
     */
    public function forUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => 'user',
            'entity_id' => $userId,
        ]);
    }

    /**
     * Create an execution with specific duration.
     */
    public function withDuration(int $seconds): static
    {
        return $this->state(function (array $attributes) use ($seconds) {
            $startedAt = $attributes['started_at'] ?? $this->faker->dateTimeBetween('-1 week', 'now');
            $completedAt = (clone $startedAt)->modify("+{$seconds} seconds");

            return [
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'status' => 'completed',
                'execution_result' => array_merge(
                    $this->generateExecutionResult(),
                    ['execution_time_seconds' => $seconds]
                ),
            ];
        });
    }
}

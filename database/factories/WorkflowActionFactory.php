<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WorkflowAction;
use App\Models\WorkflowExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkflowAction>
 */
final class WorkflowActionFactory extends Factory
{
    protected $model = WorkflowAction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'running', 'completed', 'failed']);
        $actionType = $this->faker->randomElement(['assign_ticket', 'update_ticket', 'send_notification', 'ai_action']);

        return [
            'execution_id' => WorkflowExecution::factory(),
            'action_type' => $actionType,
            'action_data' => $this->generateActionData($actionType),
            'status' => $status,
            'started_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'completed_at' => $status === 'completed' ? $this->faker->dateTimeBetween('-30 minutes', 'now') : null,
            'result' => $status === 'completed' ? $this->generateActionResult($actionType) : null,
            'error_message' => $status === 'failed' ? $this->faker->sentence() : null,
            'sequence_number' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * Generate action data based on action type.
     *
     * @return array<string, mixed>
     */
    private function generateActionData(string $actionType): array
    {
        return match ($actionType) {
            'assign_ticket' => [
                'agent_id' => $this->faker->numberBetween(1, 10),
                'department_id' => $this->faker->numberBetween(1, 5),
                'assignment_reason' => $this->faker->sentence(),
            ],
            'update_ticket' => [
                'updates' => [
                    'priority_id' => $this->faker->numberBetween(1, 5),
                    'status' => $this->faker->randomElement(['open', 'in_progress', 'resolved']),
                    'subject' => $this->faker->sentence(),
                ],
            ],
            'send_notification' => [
                'recipient_type' => $this->faker->randomElement(['assigned_agent', 'customer', 'department_managers']),
                'message' => $this->faker->sentence(),
                'template' => $this->faker->randomElement(['ticket_assigned', 'priority_changed', 'custom']),
            ],
            'ai_action' => [
                'ai_type' => $this->faker->randomElement(['categorize', 'suggest_response', 'predict_escalation']),
                'confidence_threshold' => $this->faker->randomFloat(2, 0.5, 1.0),
                'parameters' => [
                    'use_ml_model' => $this->faker->boolean(),
                    'include_sentiment' => $this->faker->boolean(),
                ],
            ],
            default => [
                'generic_config' => $this->faker->words(3, true),
            ],
        };
    }

    /**
     * Generate action result based on action type.
     *
     * @return array<string, mixed>
     */
    private function generateActionResult(string $actionType): array
    {
        $baseResult = [
            'success' => true,
            'execution_time_ms' => $this->faker->numberBetween(100, 5000),
            'timestamp' => now()->toISOString(),
        ];

        $specificResult = match ($actionType) {
            'assign_ticket' => [
                'assigned_to' => $this->faker->name(),
                'previous_assignee' => $this->faker->optional()->name(),
                'department_changed' => $this->faker->boolean(),
            ],
            'update_ticket' => [
                'fields_updated' => $this->faker->randomElements(['priority', 'status', 'subject'], 2),
                'changes_made' => [
                    'priority' => ['from' => 'normal', 'to' => 'high'],
                    'status' => ['from' => 'open', 'to' => 'in_progress'],
                ],
            ],
            'send_notification' => [
                'notifications_sent' => $this->faker->numberBetween(1, 3),
                'delivery_method' => $this->faker->randomElement(['email', 'sms', 'push']),
                'recipients' => $this->faker->randomElements(['user@example.com', 'manager@example.com'], 2),
            ],
            'ai_action' => [
                'ai_response' => [
                    'confidence' => $this->faker->randomFloat(2, 0.7, 0.99),
                    'category' => $this->faker->randomElement(['technical', 'billing', 'support']),
                    'sentiment' => $this->faker->randomElement(['positive', 'negative', 'neutral']),
                ],
                'model_used' => $this->faker->randomElement(['gpt-4', 'claude-3', 'gemini-pro']),
            ],
            default => [
                'generic_result' => 'Action completed successfully',
            ],
        };

        return array_merge($baseResult, $specificResult);
    }

    /**
     * Create a pending action.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'started_at' => null,
            'completed_at' => null,
            'result' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Create a running action.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'started_at' => $this->faker->dateTimeBetween('-10 minutes', 'now'),
            'completed_at' => null,
            'result' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Create a completed action.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = $attributes['started_at'] ?? $this->faker->dateTimeBetween('-1 hour', 'now');
            $completedAt = $this->faker->dateTimeBetween($startedAt, 'now');

            return [
                'status' => 'completed',
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'result' => $this->generateActionResult($attributes['action_type'] ?? 'assign_ticket'),
                'error_message' => null,
            ];
        });
    }

    /**
     * Create a failed action.
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = $attributes['started_at'] ?? $this->faker->dateTimeBetween('-1 hour', 'now');
            $completedAt = $this->faker->dateTimeBetween($startedAt, 'now');

            return [
                'status' => 'failed',
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'result' => null,
                'error_message' => $this->faker->randomElement([
                    'Permission denied for action',
                    'Network timeout during execution',
                    'Invalid configuration parameters',
                    'Target entity not found',
                    'Action execution limit exceeded',
                ]),
            ];
        });
    }

    /**
     * Create an assign ticket action.
     */
    public function assignTicket(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'assign_ticket',
            'action_data' => $this->generateActionData('assign_ticket'),
        ]);
    }

    /**
     * Create an update ticket action.
     */
    public function updateTicket(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'update_ticket',
            'action_data' => $this->generateActionData('update_ticket'),
        ]);
    }

    /**
     * Create a send notification action.
     */
    public function sendNotification(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'send_notification',
            'action_data' => $this->generateActionData('send_notification'),
        ]);
    }

    /**
     * Create an AI action.
     */
    public function aiAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'ai_action',
            'action_data' => $this->generateActionData('ai_action'),
        ]);
    }

    /**
     * Create an action for a specific execution.
     */
    public function forExecution(WorkflowExecution $execution): static
    {
        return $this->state(fn (array $attributes) => [
            'execution_id' => $execution->id,
        ]);
    }

    /**
     * Create an action with specific sequence number.
     */
    public function withSequence(int $sequenceNumber): static
    {
        return $this->state(fn (array $attributes) => [
            'sequence_number' => $sequenceNumber,
        ]);
    }

    /**
     * Create an action with specific duration.
     */
    public function withDuration(int $milliseconds): static
    {
        return $this->state(function (array $attributes) use ($milliseconds) {
            $startedAt = $attributes['started_at'] ?? $this->faker->dateTimeBetween('-1 hour', 'now');
            $completedAt = (clone $startedAt)->modify("+{$milliseconds} milliseconds");

            return [
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'status' => 'completed',
                'result' => array_merge(
                    $this->generateActionResult($attributes['action_type'] ?? 'assign_ticket'),
                    ['execution_time_ms' => $milliseconds]
                ),
            ];
        });
    }
}

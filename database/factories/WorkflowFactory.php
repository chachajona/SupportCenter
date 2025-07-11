<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workflow>
 */
final class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'trigger_type' => $this->faker->randomElement(['manual', 'automatic', 'schedule', 'webhook']),
            'trigger_conditions' => $this->generateTriggerConditions(),
            'workflow_data' => $this->generateWorkflowData(),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'created_by' => User::factory(),
            'updated_by' => function (array $attributes) {
                return $attributes['created_by'];
            },
        ];
    }

    /**
     * Generate trigger conditions for the workflow.
     *
     * @return array<mixed>
     */
    private function generateTriggerConditions(): array
    {
        return [
            [
                'field' => $this->faker->randomElement(['priority', 'department', 'status']),
                'operator' => $this->faker->randomElement(['=', '!=', '>', '<', 'contains']),
                'value' => $this->faker->word(),
            ],
        ];
    }

    /**
     * Generate workflow data with nodes and connections.
     *
     * @return array<string, mixed>
     */
    private function generateWorkflowData(): array
    {
        $startNodeId = 'start-'.$this->faker->uuid();
        $actionNodeId = 'action-'.$this->faker->uuid();
        $endNodeId = 'end-'.$this->faker->uuid();

        return [
            'nodes' => [
                [
                    'id' => $startNodeId,
                    'type' => 'start',
                    'position' => ['x' => 100, 'y' => 100],
                ],
                [
                    'id' => $actionNodeId,
                    'type' => 'action',
                    'position' => ['x' => 300, 'y' => 100],
                    'data' => [
                        'type' => $this->faker->randomElement(['assign_ticket', 'update_ticket', 'send_notification']),
                        'config' => [
                            'message' => $this->faker->sentence(),
                        ],
                    ],
                ],
                [
                    'id' => $endNodeId,
                    'type' => 'end',
                    'position' => ['x' => 500, 'y' => 100],
                ],
            ],
            'connections' => [
                [
                    'id' => 'connection-1',
                    'from' => $startNodeId,
                    'to' => $actionNodeId,
                ],
                [
                    'id' => 'connection-2',
                    'from' => $actionNodeId,
                    'to' => $endNodeId,
                ],
            ],
        ];
    }

    /**
     * Create a workflow with manual trigger type.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => 'manual',
        ]);
    }

    /**
     * Create a workflow with automatic trigger type.
     */
    public function automatic(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => 'automatic',
        ]);
    }

    /**
     * Create an active workflow.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive workflow.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a workflow with AI integration nodes.
     */
    public function withAI(): static
    {
        return $this->state(function (array $attributes) {
            $aiNodeId = 'ai-'.$this->faker->uuid();

            $workflowData = $attributes['workflow_data'] ?? $this->generateWorkflowData();

            // Add AI node to existing workflow
            $workflowData['nodes'][] = [
                'id' => $aiNodeId,
                'type' => 'ai',
                'position' => ['x' => 200, 'y' => 200],
                'data' => [
                    'action' => $this->faker->randomElement(['categorize', 'suggest_response', 'predict_escalation']),
                    'config' => [
                        'confidence_threshold' => 0.8,
                    ],
                ],
            ];

            return [
                'workflow_data' => $workflowData,
            ];
        });
    }

    /**
     * Create a workflow with conditional logic.
     */
    public function withConditions(): static
    {
        return $this->state(function (array $attributes) {
            $conditionNodeId = 'condition-'.$this->faker->uuid();

            $workflowData = $attributes['workflow_data'] ?? $this->generateWorkflowData();

            // Add condition node
            $workflowData['nodes'][] = [
                'id' => $conditionNodeId,
                'type' => 'condition',
                'position' => ['x' => 200, 'y' => 150],
                'data' => [
                    'field' => 'priority',
                    'operator' => '=',
                    'value' => 'high',
                    'true_path' => 'action-escalate',
                    'false_path' => 'action-normal',
                ],
            ];

            return [
                'workflow_data' => $workflowData,
            ];
        });
    }
}

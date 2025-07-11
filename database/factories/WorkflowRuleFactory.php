<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WorkflowRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkflowRule>
 */
final class WorkflowRuleFactory extends Factory
{
    protected $model = WorkflowRule::class;

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
            'entity_type' => $this->faker->randomElement(['ticket', 'user', 'knowledge_article']),
            'conditions' => $this->generateConditions(),
            'actions' => $this->generateActions(),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'schedule' => $this->faker->randomElement([null, $this->generateSchedule()]),
            'priority' => $this->faker->numberBetween(1, 10),
            'execution_limit' => $this->faker->optional()->numberBetween(1, 100),
            'last_executed_at' => $this->faker->optional()->dateTimeBetween('-1 week', 'now'),
            'execution_count' => $this->faker->numberBetween(0, 50),
            'created_by' => \App\Models\User::factory(),
        ];
    }

    /**
     * Generate conditions for the workflow rule.
     *
     * @return array<mixed>
     */
    private function generateConditions(): array
    {
        return [
            'operator' => $this->faker->randomElement(['AND', 'OR']),
            'rules' => [
                [
                    'field' => $this->faker->randomElement(['priority', 'status', 'department', 'created_at']),
                    'operator' => $this->faker->randomElement(['=', '!=', '>', '<', 'contains', 'not_contains']),
                    'value' => $this->faker->word(),
                ],
                [
                    'field' => $this->faker->randomElement(['subject', 'description', 'customer_email']),
                    'operator' => $this->faker->randomElement(['contains', 'not_contains', 'starts_with', 'ends_with']),
                    'value' => $this->faker->word(),
                ],
            ],
        ];
    }

    /**
     * Generate actions for the workflow rule.
     *
     * @return array<mixed>
     */
    private function generateActions(): array
    {
        return [
            [
                'type' => 'update_ticket',
                'config' => [
                    'updates' => [
                        'priority_id' => $this->faker->numberBetween(1, 5),
                        'status' => $this->faker->randomElement(['open', 'in_progress', 'resolved']),
                    ],
                ],
            ],
            [
                'type' => 'send_notification',
                'config' => [
                    'recipient_type' => $this->faker->randomElement(['assigned_agent', 'department_managers', 'customer']),
                    'message' => $this->faker->sentence(),
                ],
            ],
        ];
    }

    /**
     * Generate schedule configuration.
     *
     * @return array<string, mixed>
     */
    private function generateSchedule(): array
    {
        return [
            'type' => $this->faker->randomElement(['recurring', 'one_time']),
            'frequency' => $this->faker->randomElement(['daily', 'weekly', 'monthly']),
            'time' => $this->faker->time('H:i'),
            'timezone' => $this->faker->randomElement(['UTC', 'America/New_York', 'Europe/London']),
            'days_of_week' => $this->faker->randomElements(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], 3),
        ];
    }

    /**
     * Create a rule for tickets.
     */
    public function forTickets(): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => 'ticket',
            'conditions' => [
                'operator' => 'AND',
                'rules' => [
                    [
                        'field' => 'priority',
                        'operator' => '=',
                        'value' => 'high',
                    ],
                    [
                        'field' => 'status',
                        'operator' => '=',
                        'value' => 'open',
                    ],
                ],
            ],
            'actions' => [
                [
                    'type' => 'assign_ticket',
                    'config' => [
                        'agent_id' => $this->faker->numberBetween(1, 10),
                    ],
                ],
                [
                    'type' => 'update_ticket',
                    'config' => [
                        'updates' => [
                            'priority_id' => 5, // Critical
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Create a rule for users.
     */
    public function forUsers(): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => 'user',
            'conditions' => [
                'operator' => 'AND',
                'rules' => [
                    [
                        'field' => 'last_login_at',
                        'operator' => '<',
                        'value' => '30 days ago',
                    ],
                ],
            ],
            'actions' => [
                [
                    'type' => 'send_email',
                    'config' => [
                        'template' => 'user_inactive_reminder',
                        'subject' => 'Account Inactive - Please Login',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Create an active rule.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive rule.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a rule with schedule.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule' => $this->generateSchedule(),
        ]);
    }

    /**
     * Create a rule without schedule (event-triggered).
     */
    public function eventTriggered(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule' => null,
        ]);
    }

    /**
     * Create a rule with execution limit.
     */
    public function withExecutionLimit(int $limit): static
    {
        return $this->state(fn (array $attributes) => [
            'execution_limit' => $limit,
        ]);
    }

    /**
     * Create a rule with specific priority.
     */
    public function withPriority(int $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }

    /**
     * Create a high priority rule.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 1,
        ]);
    }

    /**
     * Create a low priority rule.
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 10,
        ]);
    }

    /**
     * Create a rule for SLA management.
     */
    public function slaManagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'SLA Escalation Rule',
            'description' => 'Automatically escalate tickets approaching SLA breach',
            'entity_type' => 'ticket',
            'conditions' => [
                'operator' => 'AND',
                'rules' => [
                    [
                        'field' => 'sla_due_at',
                        'operator' => '<',
                        'value' => '2 hours',
                    ],
                    [
                        'field' => 'status',
                        'operator' => '!=',
                        'value' => 'resolved',
                    ],
                ],
            ],
            'actions' => [
                [
                    'type' => 'update_ticket',
                    'config' => [
                        'updates' => [
                            'priority_id' => 5,
                        ],
                    ],
                ],
                [
                    'type' => 'send_notification',
                    'config' => [
                        'recipient_type' => 'department_managers',
                        'message' => 'SLA breach warning: Ticket requires immediate attention',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Create a rule for VIP customer handling.
     */
    public function vipCustomerRule(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'VIP Customer Priority Rule',
            'description' => 'Prioritize tickets from VIP customers',
            'entity_type' => 'ticket',
            'conditions' => [
                'operator' => 'AND',
                'rules' => [
                    [
                        'field' => 'customer.tier',
                        'operator' => '=',
                        'value' => 'vip',
                    ],
                ],
            ],
            'actions' => [
                [
                    'type' => 'update_ticket',
                    'config' => [
                        'updates' => [
                            'priority_id' => 4, // High priority
                        ],
                    ],
                ],
                [
                    'type' => 'assign_ticket',
                    'config' => [
                        'criteria' => 'senior_agent',
                    ],
                ],
                [
                    'type' => 'send_notification',
                    'config' => [
                        'recipient_type' => 'assigned_agent',
                        'message' => 'VIP customer ticket assigned - Please prioritize',
                    ],
                ],
            ],
        ]);
    }
}

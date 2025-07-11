<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkflowTemplate>
 */
final class WorkflowTemplateFactory extends Factory
{
    protected $model = WorkflowTemplate::class;

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
            'category' => $this->faker->randomElement(['vip_support', 'security_incident', 'general_automation', 'sla_management']),
            'template_data' => $this->generateTemplateData(),
            'is_system_template' => $this->faker->boolean(20), // 20% chance of being system template
        ];
    }

    /**
     * Generate template data with predefined workflow structure.
     *
     * @return array<string, mixed>
     */
    private function generateTemplateData(): array
    {
        $startNodeId = 'start-template';
        $actionNodeId = 'action-template';
        $endNodeId = 'end-template';

        return [
            'nodes' => [
                [
                    'id' => $startNodeId,
                    'type' => 'start',
                    'position' => ['x' => 100, 'y' => 100],
                    'label' => 'Start',
                ],
                [
                    'id' => $actionNodeId,
                    'type' => 'action',
                    'position' => ['x' => 300, 'y' => 100],
                    'label' => 'Action',
                    'data' => [
                        'type' => 'assign_ticket',
                        'config' => [
                            'assign_to_department' => 'support',
                            'message' => 'Ticket assigned automatically',
                        ],
                    ],
                ],
                [
                    'id' => $endNodeId,
                    'type' => 'end',
                    'position' => ['x' => 500, 'y' => 100],
                    'label' => 'End',
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
            'trigger' => [
                'type' => 'manual',
                'conditions' => [],
            ],
            'metadata' => [
                'author' => 'System',
                'version' => '1.0',
                'tags' => ['automation', 'template'],
            ],
        ];
    }

    /**
     * Create a VIP support template.
     */
    public function vipSupport(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'VIP Customer Support Workflow',
            'category' => 'vip_support',
            'description' => 'Automated workflow for VIP customer support tickets',
            'template_data' => $this->generateVipSupportTemplate(),
        ]);
    }

    /**
     * Create a security incident template.
     */
    public function securityIncident(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Security Incident Response',
            'category' => 'security_incident',
            'description' => 'Automated security incident escalation workflow',
            'template_data' => $this->generateSecurityIncidentTemplate(),
        ]);
    }

    /**
     * Create a system template.
     */
    public function systemTemplate(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system_template' => true,
        ]);
    }

    /**
     * Generate VIP support template data.
     *
     * @return array<string, mixed>
     */
    private function generateVipSupportTemplate(): array
    {
        return [
            'nodes' => [
                [
                    'id' => 'start-vip',
                    'type' => 'start',
                    'position' => ['x' => 100, 'y' => 100],
                ],
                [
                    'id' => 'priority-escalation',
                    'type' => 'action',
                    'position' => ['x' => 300, 'y' => 100],
                    'data' => [
                        'type' => 'update_ticket',
                        'config' => [
                            'updates' => ['priority_id' => 5], // Critical priority
                        ],
                    ],
                ],
                [
                    'id' => 'manager-notification',
                    'type' => 'action',
                    'position' => ['x' => 500, 'y' => 100],
                    'data' => [
                        'type' => 'send_notification',
                        'config' => [
                            'recipient_type' => 'department_managers',
                            'message' => 'VIP customer ticket requires immediate attention',
                        ],
                    ],
                ],
                [
                    'id' => 'end-vip',
                    'type' => 'end',
                    'position' => ['x' => 700, 'y' => 100],
                ],
            ],
            'connections' => [
                ['from' => 'start-vip', 'to' => 'priority-escalation'],
                ['from' => 'priority-escalation', 'to' => 'manager-notification'],
                ['from' => 'manager-notification', 'to' => 'end-vip'],
            ],
            'trigger' => [
                'type' => 'automatic',
                'conditions' => [
                    ['field' => 'customer_tier', 'operator' => '=', 'value' => 'vip'],
                ],
            ],
        ];
    }

    /**
     * Generate security incident template data.
     *
     * @return array<string, mixed>
     */
    private function generateSecurityIncidentTemplate(): array
    {
        return [
            'nodes' => [
                [
                    'id' => 'start-security',
                    'type' => 'start',
                    'position' => ['x' => 100, 'y' => 100],
                ],
                [
                    'id' => 'ai-categorize',
                    'type' => 'ai',
                    'position' => ['x' => 300, 'y' => 100],
                    'data' => [
                        'action' => 'categorize',
                        'config' => [
                            'confidence_threshold' => 0.9,
                        ],
                    ],
                ],
                [
                    'id' => 'immediate-escalation',
                    'type' => 'action',
                    'position' => ['x' => 500, 'y' => 100],
                    'data' => [
                        'type' => 'update_ticket',
                        'config' => [
                            'updates' => [
                                'priority_id' => 5,
                                'department_id' => 'security',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'security-team-alert',
                    'type' => 'action',
                    'position' => ['x' => 700, 'y' => 100],
                    'data' => [
                        'type' => 'send_notification',
                        'config' => [
                            'recipient_type' => 'security_team',
                            'message' => 'Security incident detected - immediate response required',
                        ],
                    ],
                ],
                [
                    'id' => 'end-security',
                    'type' => 'end',
                    'position' => ['x' => 900, 'y' => 100],
                ],
            ],
            'connections' => [
                ['from' => 'start-security', 'to' => 'ai-categorize'],
                ['from' => 'ai-categorize', 'to' => 'immediate-escalation'],
                ['from' => 'immediate-escalation', 'to' => 'security-team-alert'],
                ['from' => 'security-team-alert', 'to' => 'end-security'],
            ],
            'trigger' => [
                'type' => 'automatic',
                'conditions' => [
                    ['field' => 'subject', 'operator' => 'contains', 'value' => 'security'],
                    ['field' => 'category', 'operator' => '=', 'value' => 'security_incident'],
                ],
            ],
        ];
    }
}

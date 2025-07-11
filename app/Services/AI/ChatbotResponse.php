<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Collection;

readonly class ChatbotResponse
{
    public function __construct(
        public string $message,
        public array $intent,
        public Collection $knowledgeArticles,
        public float $confidence,
        public bool $shouldEscalate,
        public array $suggestedActions = []
    ) {}

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'intent' => $this->intent,
            'knowledge_articles' => $this->knowledgeArticles->map(function ($article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'url' => route('knowledge.show', $article->id),
                    'excerpt' => substr(strip_tags($article->content), 0, 200).'...',
                ];
            }),
            'confidence' => round($this->confidence, 2),
            'should_escalate' => $this->shouldEscalate,
            'suggested_actions' => $this->suggestedActions,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get formatted confidence percentage
     */
    public function getConfidencePercentage(): string
    {
        return number_format($this->confidence * 100, 1).'%';
    }

    /**
     * Check if this is a high confidence response
     */
    public function isHighConfidence(): bool
    {
        return $this->confidence >= 0.8;
    }

    /**
     * Check if this is a low confidence response
     */
    public function isLowConfidence(): bool
    {
        return $this->confidence < 0.6;
    }

    /**
     * Get the primary intent category
     */
    public function getIntentCategory(): string
    {
        return $this->intent['category'] ?? 'unknown';
    }

    /**
     * Get urgency level from intent
     */
    public function getUrgencyLevel(): string
    {
        return $this->intent['urgency'] ?? 'normal';
    }

    /**
     * Check if the response contains knowledge base results
     */
    public function hasKnowledgeResults(): bool
    {
        return $this->knowledgeArticles->count() > 0;
    }

    /**
     * Get action buttons for the UI
     */
    public function getActionButtons(): array
    {
        $buttons = [];

        foreach ($this->suggestedActions as $action) {
            $buttons[] = match ($action) {
                'search_knowledge' => [
                    'text' => 'Search Knowledge Base',
                    'type' => 'secondary',
                    'action' => 'search_knowledge',
                ],
                'create_ticket' => [
                    'text' => 'Create Support Ticket',
                    'type' => 'primary',
                    'action' => 'create_ticket',
                ],
                'escalate_to_human' => [
                    'text' => 'Talk to Human Agent',
                    'type' => 'primary',
                    'action' => 'escalate_to_human',
                ],
                'troubleshoot_guide' => [
                    'text' => 'View Troubleshooting Guide',
                    'type' => 'secondary',
                    'action' => 'troubleshoot_guide',
                ],
                'escalate_to_technical' => [
                    'text' => 'Contact Technical Support',
                    'type' => 'primary',
                    'action' => 'escalate_to_technical',
                ],
                'escalate_to_billing' => [
                    'text' => 'Contact Billing Support',
                    'type' => 'primary',
                    'action' => 'escalate_to_billing',
                ],
                'view_account' => [
                    'text' => 'View Account Details',
                    'type' => 'secondary',
                    'action' => 'view_account',
                ],
                'escalate_to_manager' => [
                    'text' => 'Speak to Manager',
                    'type' => 'primary',
                    'action' => 'escalate_to_manager',
                ],
                default => [
                    'text' => ucwords(str_replace('_', ' ', $action)),
                    'type' => 'secondary',
                    'action' => $action,
                ],
            };
        }

        return $buttons;
    }
}

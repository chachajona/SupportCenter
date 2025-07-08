<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Models\Ticket;

interface AIProviderInterface
{
    /**
     * Perform ticket categorization and return a structured array or null on failure.
     */
    public function categorize(string $systemPrompt, string $userPrompt): ?array;

    /**
     * Generate response suggestions for a ticket. Raw string (markdown/text) or null on failure.
     */
    public function suggestResponses(string $systemPrompt, string $userPrompt): ?string;

    /**
     * Predict escalation probability and return associative array with probability, confidence, factors.
     */
    public function predictEscalation(string $systemPrompt, string $userPrompt): ?array;

    /**
     * Generate text embeddings for semantic similarity search.
     */
    public function generateEmbeddings(string $text): array;

    /**
     * Check whether the provider has the required configuration (e.g., API keys).
     */
    public function isConfigured(): bool;
}

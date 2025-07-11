<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AI\Providers\AIProviderInterface;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GeminiProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatbotService
{
    protected AIProviderInterface $provider;

    protected string $providerName;

    public function __construct()
    {
        $this->providerName = config('services.ai_provider', 'gemini');
        $this->provider = $this->getProviderClient();
    }

    /**
     * Process a user message and return chatbot response
     */
    public function processMessage(string $message, array $context = []): ChatbotResponse
    {
        try {
            // Analyze user intent
            $intent = $this->analyzeIntent($message, $context);

            // Search knowledge base for relevant information
            $knowledgeResults = $this->searchKnowledgeBase($message);

            // Generate contextual response
            $response = $this->generateResponse($message, $intent, $knowledgeResults, $context);

            // Determine if escalation is needed
            $shouldEscalate = $this->shouldEscalateToHuman($intent, $context);

            return new ChatbotResponse(
                message: $response,
                intent: $intent,
                knowledgeArticles: $knowledgeResults,
                confidence: $this->calculateConfidence($intent, $knowledgeResults),
                shouldEscalate: $shouldEscalate,
                suggestedActions: $this->getSuggestedActions($intent, $context)
            );

        } catch (\Exception $e) {
            Log::error('Chatbot processing failed', [
                'message' => $message,
                'error' => $e->getMessage(),
            ]);

            return new ChatbotResponse(
                message: "I'm sorry, I'm having trouble understanding your request right now. Would you like me to connect you with a human agent?",
                intent: ['category' => 'error', 'confidence' => 0.5],
                knowledgeArticles: collect(),
                confidence: 0.5,
                shouldEscalate: true,
                suggestedActions: ['escalate_to_human']
            );
        }
    }

    /**
     * Escalate conversation to human agent with full context
     */
    public function escalateToHuman(Conversation $conversation, ?User $user = null): Ticket
    {
        $conversationHistory = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn(ConversationMessage $msg) => "{$msg->sender_type}: {$msg->content}")
            ->join("\n");

        $ticket = Ticket::create([
            'subject' => $this->generateTicketSubject($conversation),
            'description' => $this->generateTicketDescription($conversation, $conversationHistory),
            'priority_id' => $this->determinePriority($conversation),
            'status_id' => 1, // Open
            'created_by' => $user?->id,
            'department_id' => $this->suggestDepartment($conversation),
        ]);

        // Update conversation with ticket reference
        $conversation->update([
            'status' => 'escalated',
            'escalated_to_ticket_id' => $ticket->id,
            'escalated_at' => now(),
        ]);

        Log::info('Conversation escalated to human agent', [
            'conversation_id' => $conversation->id,
            'ticket_id' => $ticket->id,
            'user_id' => $user?->id,
        ]);

        return $ticket;
    }

    /**
     * Analyze user intent from message
     */
    protected function analyzeIntent(string $message, array $context): array
    {
        $cacheKey = 'chatbot_intent_' . md5($message . serialize($context));

        return Cache::remember($cacheKey, 300, function () use ($message, $context) {
            if (!$this->provider->isConfigured()) {
                return $this->fallbackIntentAnalysis($message);
            }

            $systemPrompt = $this->getIntentAnalysisPrompt();
            $userPrompt = $this->buildIntentPrompt($message, $context);

            $result = $this->provider->categorize($systemPrompt, $userPrompt);

            return $result ?? $this->fallbackIntentAnalysis($message);
        });
    }

    /**
     * Search knowledge base for relevant articles
     */
    protected function searchKnowledgeBase(string $query): Collection
    {
        return KnowledgeArticle::published()
            ->whereRaw('MATCH(title, content) AGAINST (? IN NATURAL LANGUAGE MODE)', [$query])
            ->orderByRaw('MATCH(title, content) AGAINST (? IN NATURAL LANGUAGE MODE) DESC', [$query])
            ->limit(5)
            ->get();
    }

    /**
     * Generate chatbot response
     */
    protected function generateResponse(string $message, array $intent, Collection $knowledgeResults, array $context): string
    {
        if (!$this->provider->isConfigured()) {
            return $this->fallbackResponse($intent, $knowledgeResults);
        }

        $systemPrompt = $this->getResponseGenerationPrompt();
        $userPrompt = $this->buildResponsePrompt($message, $intent, $knowledgeResults, $context);

        $response = $this->provider->suggestResponses($systemPrompt, $userPrompt);

        return $response ?? $this->fallbackResponse($intent, $knowledgeResults);
    }

    /**
     * Determine if conversation should escalate to human
     */
    protected function shouldEscalateToHuman(array $intent, array $context): bool
    {
        $confidence = $intent['confidence'] ?? 0.5;
        $category = $intent['category'] ?? 'unknown';

        // Low confidence responses should escalate
        if ($confidence < 0.6) {
            return true;
        }

        // Complex categories should escalate
        $complexCategories = ['billing_issue', 'account_problem', 'technical_error', 'complaint'];
        if (in_array($category, $complexCategories)) {
            return true;
        }

        // Multiple failed attempts should escalate
        $failedAttempts = $context['failed_attempts'] ?? 0;
        if ($failedAttempts >= 2) {
            return true;
        }

        return false;
    }

    /**
     * Calculate response confidence score
     */
    protected function calculateConfidence(array $intent, Collection $knowledgeResults): float
    {
        $intentConfidence = $intent['confidence'] ?? 0.5;
        $knowledgeScore = $knowledgeResults->count() > 0 ? 0.8 : 0.3;

        return ($intentConfidence + $knowledgeScore) / 2;
    }

    /**
     * Get suggested actions based on intent
     */
    protected function getSuggestedActions(array $intent, array $context): array
    {
        $category = $intent['category'] ?? 'unknown';

        return match ($category) {
            'question' => ['search_knowledge', 'create_ticket'],
            'technical_issue' => ['troubleshoot_guide', 'escalate_to_technical'],
            'billing_issue' => ['escalate_to_billing', 'view_account'],
            'complaint' => ['escalate_to_manager', 'create_ticket'],
            default => ['search_knowledge', 'escalate_to_human'],
        };
    }

    /**
     * Get provider client
     */
    protected function getProviderClient(): AIProviderInterface
    {
        return match ($this->providerName) {
            'anthropic' => new AnthropicProvider(
                config('services.anthropic.api_key'),
                config('services.anthropic.base_url')
            ),
            default => new GeminiProvider(
                config('services.gemini.api_key'),
                config('services.gemini.base_url')
            ),
        };
    }

    /**
     * Generate ticket subject from conversation
     */
    protected function generateTicketSubject(Conversation $conversation): string
    {
        /** @var ConversationMessage|null $firstMessage */
        $firstMessage = $conversation->messages()->oldest()->first();
        $subject = $firstMessage ? substr($firstMessage->content, 0, 80) : 'Support Request';

        return 'Chatbot Escalation: ' . $subject;
    }

    /**
     * Generate ticket description from conversation
     */
    protected function generateTicketDescription(Conversation $conversation, string $conversationHistory): string
    {
        return "This ticket was escalated from a chatbot conversation.\n\n" .
            "**Conversation History:**\n" . $conversationHistory . "\n\n" .
            '**Escalation Reason:** ' . ($conversation->escalation_reason ?? 'User requested human assistance') . "\n" .
            '**Customer needs human assistance to resolve this inquiry.**';
    }

    /**
     * Determine ticket priority from conversation
     */
    protected function determinePriority(Conversation $conversation): int
    {
        // Analyze conversation for urgency indicators
        $urgentKeywords = ['urgent', 'critical', 'emergency', 'down', 'broken', 'error'];
        $content = $conversation->messages()->pluck('content')->join(' ');

        foreach ($urgentKeywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                return 4; // High priority
            }
        }

        return 2; // Normal priority
    }

    /**
     * Suggest department based on conversation
     */
    protected function suggestDepartment(Conversation $conversation): ?int
    {
        // Simple keyword-based department suggestion
        $content = strtolower($conversation->messages()->pluck('content')->join(' '));

        if (str_contains($content, 'bill') || str_contains($content, 'payment') || str_contains($content, 'invoice')) {
            return 2; // Billing department
        }

        if (str_contains($content, 'technical') || str_contains($content, 'error') || str_contains($content, 'bug')) {
            return 3; // Technical department
        }

        return 1; // General support
    }

    /**
     * System prompts for AI operations
     */
    protected function getIntentAnalysisPrompt(): string
    {
        return 'You are an AI assistant that analyzes customer support messages to understand user intent. Classify the message and return confidence scores.';
    }

    protected function getResponseGenerationPrompt(): string
    {
        return 'You are a helpful customer support chatbot. Provide helpful, friendly, and accurate responses based on the knowledge base and user intent. Keep responses concise but informative.';
    }

    /**
     * Build prompts for AI processing
     */
    protected function buildIntentPrompt(string $message, array $context): string
    {
        return "Analyze this customer message and determine intent:\n\n" .
            "Message: {$message}\n\n" .
            "Return JSON with:\n" .
            "- category: (question, technical_issue, billing_issue, complaint, compliment, other)\n" .
            "- urgency: (low, normal, high, critical)\n" .
            "- confidence: (0.0-1.0)\n" .
            '- keywords: array of key terms';
    }

    protected function buildResponsePrompt(string $message, array $intent, Collection $knowledgeResults, array $context): string
    {
        $knowledge = $knowledgeResults->map(function ($article) {
            return "Title: {$article->title}\nSummary: " . substr($article->content, 0, 200) . '...';
        })->join("\n\n");

        return "Generate a helpful response to this customer message:\n\n" .
            "Customer Message: {$message}\n" .
            'Intent: ' . json_encode($intent) . "\n\n" .
            "Relevant Knowledge Base Articles:\n{$knowledge}\n\n" .
            'Provide a helpful, friendly response that addresses their concern. ' .
            'Reference knowledge base articles when appropriate.';
    }

    /**
     * Fallback methods when AI is unavailable
     */
    protected function fallbackIntentAnalysis(string $message): array
    {
        $text = strtolower($message);

        if (str_contains($text, 'bill') || str_contains($text, 'payment')) {
            return ['category' => 'billing_issue', 'confidence' => 0.7];
        }

        if (str_contains($text, 'error') || str_contains($text, 'bug') || str_contains($text, 'broken')) {
            return ['category' => 'technical_issue', 'confidence' => 0.7];
        }

        return ['category' => 'question', 'confidence' => 0.5];
    }

    protected function fallbackResponse(array $intent, Collection $knowledgeResults): string
    {
        if ($knowledgeResults->count() > 0) {
            $article = $knowledgeResults->first();

            return "I found this information that might help: {$article->title}. " .
                'You can read more at: ' . route('knowledge.show', $article->id) . "\n\n" .
                'Would you like me to connect you with a human agent for more assistance?';
        }

        return 'I understand you need help. Let me connect you with one of our support agents who can assist you better.';
    }
}

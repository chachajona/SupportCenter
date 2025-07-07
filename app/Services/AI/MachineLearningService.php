<?php

namespace App\Services\AI;

use App\Models\AIPrediction;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MachineLearningService
{
    protected ?string $geminiApiKey;

    protected string $geminiBaseUrl;

    protected array $supportedModels = [
        'gemini-1.5-flash' => 'gemini-1.5-flash',
        'gemini-1.5-pro' => 'gemini-1.5-pro',
        'text-embedding-004' => 'text-embedding-004',
    ];

    // Added: Multi-provider support (Anthropic Claude)
    protected ?string $anthropicApiKey;

    protected string $anthropicBaseUrl;

    // Currently selected provider (gemini | anthropic)
    protected string $provider;

    public function __construct()
    {
        // Determine active provider (defaults to gemini)
        $this->provider = config('services.ai_provider', 'gemini');

        // Gemini configuration
        $this->geminiApiKey = config('services.gemini.api_key');
        $this->geminiBaseUrl = config('services.gemini.base_url');

        // Anthropic configuration
        $this->anthropicApiKey = config('services.anthropic.api_key');
        $this->anthropicBaseUrl = config('services.anthropic.base_url');

        if ($this->provider === 'gemini' && ! $this->geminiApiKey) {
            Log::warning('Gemini API key not configured. AI features will use fallback methods.');
        }

        if ($this->provider === 'anthropic' && ! $this->anthropicApiKey) {
            Log::warning('Anthropic API key not configured. AI features will use fallback methods.');
        }
    }

    /**
     * Categorize ticket using AI analysis
     */
    public function categorizeTicket(string $subject, string $description): array
    {
        $cacheKey = 'ai_categorize_'.md5($subject.$description);

        return Cache::store('ai_cache')->remember($cacheKey, 3600, function () use ($subject, $description) {
            // Check if API key is available
            if (! $this->geminiApiKey && ! $this->anthropicApiKey) {
                Log::info('Gemini and Anthropic API keys not available, using fallback categorization');

                return $this->fallbackCategorization($subject, $description);
            }

            try {
                $prompt = $this->buildCategorizationPrompt($subject, $description);

                // NEW: Anthropic provider support
                if ($this->provider === 'anthropic') {
                    try {
                        $response = Http::withHeaders([
                            'x-api-key' => $this->anthropicApiKey,
                            'anthropic-version' => '2023-06-01',
                        ])->timeout(config('services.anthropic.timeout', 30))
                            ->post($this->anthropicBaseUrl.'/messages', [
                                'model' => config('services.anthropic.default_model'),
                                'system' => $this->getSystemPrompt(),
                                'messages' => [
                                    ['role' => 'user', 'content' => $prompt],
                                ],
                                'max_tokens' => (int) config('services.anthropic.max_tokens', 800),
                                'temperature' => (float) config('services.anthropic.temperature', 0.3),
                            ]);

                        if ($response->successful()) {
                            $data = $response->json();
                            $content = $data['content'][0]['text'] ?? '';
                            $result = json_decode($content, true);

                            if (json_last_error() === JSON_ERROR_NONE) {
                                $this->storePrediction(null, 'category', $result, $result['confidence'] ?? 0.8);

                                return $result;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Anthropic AI Categorization failed', [
                            'subject' => $subject,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $response = Http::timeout(30)
                    ->post($this->geminiBaseUrl.'/models/'.config('services.gemini.default_model').':generateContent?key='.$this->geminiApiKey, [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $this->getSystemPrompt()."\n\n".$prompt],
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'temperature' => (float) config('services.gemini.temperature'),
                            'maxOutputTokens' => (int) config('services.gemini.max_tokens'),
                            'responseMimeType' => 'application/json',
                        ],
                        'safetySettings' => $this->getSafetySettings(),
                    ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $content = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $result = json_decode($content, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Store prediction for learning
                        $this->storePrediction(null, 'category', $result, $result['confidence'] ?? 0.8);

                        return $result;
                    }
                }
            } catch (\Exception $e) {
                Log::error('AI Categorization failed', [
                    'subject' => $subject,
                    'error' => $e->getMessage(),
                ]);
            }

            // Fallback to rule-based categorization
            return $this->fallbackCategorization($subject, $description);
        });
    }

    /**
     * Suggest responses based on ticket content and knowledge base
     */
    public function suggestResponses(Ticket $ticket): array
    {
        $cacheKey = 'ai_responses_'.$ticket->id;

        return Cache::store('ai_cache')->remember($cacheKey, 1800, function () use ($ticket) {
            // Check if API key is available
            if (! $this->geminiApiKey && ! $this->anthropicApiKey) {
                Log::info('Gemini and Anthropic API keys not available, returning empty suggestions');

                return ['suggestions' => [], 'confidence' => 0.0];
            }

            try {
                // Get relevant knowledge articles first
                $relevantArticles = $this->findRelevantKnowledgeArticles($ticket);

                $prompt = $this->buildResponseSuggestionPrompt($ticket, $relevantArticles);

                if ($this->provider === 'anthropic') {
                    try {
                        $response = Http::withHeaders([
                            'x-api-key' => $this->anthropicApiKey,
                            'anthropic-version' => '2023-06-01',
                        ])->timeout(config('services.anthropic.timeout', 45))
                            ->post($this->anthropicBaseUrl.'/messages', [
                                'model' => config('services.anthropic.default_model'),
                                'system' => $this->getResponseSystemPrompt(),
                                'messages' => [
                                    ['role' => 'user', 'content' => $prompt],
                                ],
                                'max_tokens' => 800,
                                'temperature' => (float) config('services.anthropic.temperature', 0.4),
                            ]);

                        if ($response->successful()) {
                            $data = $response->json();
                            $content = $data['content'][0]['text'] ?? '';

                            return [
                                'suggestions' => $this->parseResponseSuggestions($content),
                                'confidence' => 0.85,
                                'knowledge_articles' => $relevantArticles->pluck('id')->toArray(),
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error('Anthropic AI Response Suggestion failed', [
                            'ticket_id' => $ticket->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $response = Http::timeout(45)
                    ->post($this->geminiBaseUrl.'/models/'.config('services.gemini.default_model').':generateContent?key='.$this->geminiApiKey, [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $this->getResponseSystemPrompt()."\n\n".$prompt],
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.4,
                            'maxOutputTokens' => 800,
                        ],
                        'safetySettings' => $this->getSafetySettings(),
                    ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $suggestions = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

                    return [
                        'suggestions' => $this->parseResponseSuggestions($suggestions),
                        'confidence' => 0.85,
                        'knowledge_articles' => $relevantArticles->pluck('id')->toArray(),
                    ];
                }
            } catch (\Exception $e) {
                Log::error('AI Response Suggestion failed', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return ['suggestions' => [], 'confidence' => 0.0];
        });
    }

    /**
     * Predict escalation probability for a ticket
     */
    public function predictEscalation(Ticket $ticket): float
    {
        $cacheKey = 'ai_escalation_'.$ticket->id;

        return Cache::store('prediction_cache')->remember($cacheKey, 600, function () use ($ticket) {
            // Check if API key is available
            if (! $this->geminiApiKey && ! $this->anthropicApiKey) {
                Log::info('Gemini and Anthropic API keys not available, returning default escalation probability');

                return 0.5; // Default neutral probability
            }

            try {
                $features = $this->extractEscalationFeatures($ticket);
                $prompt = $this->buildEscalationPrompt($ticket, $features);

                if ($this->provider === 'anthropic') {
                    try {
                        $response = Http::withHeaders([
                            'x-api-key' => $this->anthropicApiKey,
                            'anthropic-version' => '2023-06-01',
                        ])->timeout(config('services.anthropic.timeout', 20))
                            ->post($this->anthropicBaseUrl.'/messages', [
                                'model' => config('services.anthropic.default_model'),
                                'system' => $this->getEscalationSystemPrompt(),
                                'messages' => [
                                    ['role' => 'user', 'content' => $prompt],
                                ],
                                'max_tokens' => 200,
                                'temperature' => (float) config('services.anthropic.temperature', 0.2),
                            ]);

                        if ($response->successful()) {
                            $data = $response->json();
                            $content = $data['content'][0]['text'] ?? '';
                            $result = json_decode($content, true);

                            if (json_last_error() === JSON_ERROR_NONE) {
                                $probability = (float) ($result['escalation_probability'] ?? 0.5);

                                // Store prediction
                                $this->storePrediction($ticket->id, 'escalation', [
                                    'probability' => $probability,
                                    'factors' => $result['factors'] ?? [],
                                ], $result['confidence'] ?? 0.7);

                                return $probability;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Anthropic AI Escalation Prediction failed', [
                            'ticket_id' => $ticket->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $response = Http::timeout(20)
                    ->post($this->geminiBaseUrl.'/models/'.config('services.gemini.default_model').':generateContent?key='.$this->geminiApiKey, [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $this->getEscalationSystemPrompt()."\n\n".$prompt],
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.2,
                            'maxOutputTokens' => 200,
                            'responseMimeType' => 'application/json',
                        ],
                        'safetySettings' => $this->getSafetySettings(),
                    ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $content = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $result = json_decode($content, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $probability = (float) ($result['escalation_probability'] ?? 0.5);

                        // Store prediction
                        $this->storePrediction($ticket->id, 'escalation', [
                            'probability' => $probability,
                            'factors' => $result['factors'] ?? [],
                        ], $result['confidence'] ?? 0.7);

                        return $probability;
                    }
                }
            } catch (\Exception $e) {
                Log::error('AI Escalation Prediction failed', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return 0.5; // Default neutral probability
        });
    }

    /**
     * Generate text embeddings for semantic search
     */
    public function generateEmbeddings(string $text): array
    {
        $cacheKey = 'embeddings_'.md5($text);

        return Cache::store('vector_cache')->remember($cacheKey, 86400, function () use ($text) {
            // Check if API key is available
            if (! $this->geminiApiKey && ! $this->anthropicApiKey) {
                Log::info('Gemini and Anthropic API keys not available, cannot generate embeddings');

                return [];
            }

            try {
                $response = Http::timeout(30)
                    ->post($this->geminiBaseUrl.'/models/'.config('services.gemini.embedding_model').':embedContent?key='.$this->geminiApiKey, [
                        'content' => [
                            'parts' => [
                                ['text' => $text],
                            ],
                        ],
                        'taskType' => 'SEMANTIC_SIMILARITY',
                    ]);

                if ($response->successful()) {
                    $responseData = $response->json();

                    return $responseData['embedding']['values'] ?? [];
                }
            } catch (\Exception $e) {
                Log::error('Embedding generation failed', [
                    'text_length' => strlen($text),
                    'error' => $e->getMessage(),
                ]);
            }

            return [];
        });
    }

    /**
     * Store AI prediction for learning and improvement
     */
    protected function storePrediction(?int $ticketId, string $type, array $prediction, float $confidence): void
    {
        try {
            AIPrediction::create([
                'ticket_id' => $ticketId,
                'prediction_type' => $type,
                'predicted_value' => $prediction,
                'confidence_score' => $confidence,
                'model_version' => $this->provider.'-'.date('Y-m'),
                'features_used' => $this->getCurrentFeatures(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store AI prediction', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build categorization prompt for AI
     */
    protected function buildCategorizationPrompt(string $subject, string $description): string
    {
        return "Analyze this support ticket and provide categorization in JSON format:

Subject: {$subject}
Description: {$description}

Return JSON with:
- department: (technical, billing, general, sales)
- priority: (low, normal, high, urgent)
- category: (bug, feature_request, question, complaint, compliment)
- estimated_resolution_time: (in minutes)
- sentiment: (positive, neutral, negative)
- confidence: (0.0-1.0)";
    }

    /**
     * Build response suggestion prompt
     */
    protected function buildResponseSuggestionPrompt(Ticket $ticket, $articles): string
    {
        $knowledgeContext = $articles->map(function ($article) {
            return "Title: {$article->title}\nContent: ".substr($article->content, 0, 300).'...';
        })->join("\n\n");

        return "Generate helpful response suggestions for this support ticket:

Ticket Subject: {$ticket->subject}
Ticket Description: {$ticket->description}
Priority: {$ticket->priority->name}
Status: {$ticket->status->name}

Relevant Knowledge Base Articles:
{$knowledgeContext}

Provide 2-3 response suggestions that are helpful, professional, and reference the knowledge base when applicable.";
    }

    /**
     * System prompt for general AI operations
     */
    protected function getSystemPrompt(): string
    {
        return 'You are an AI assistant for a support center. Analyze tickets professionally and provide accurate categorization based on content. Always respond with valid JSON.';
    }

    /**
     * System prompt for response suggestions
     */
    protected function getResponseSystemPrompt(): string
    {
        return 'You are a helpful support agent assistant. Generate professional, empathetic, and solution-oriented response suggestions. Reference knowledge base articles when relevant.';
    }

    /**
     * System prompt for escalation prediction
     */
    protected function getEscalationSystemPrompt(): string
    {
        return 'You are an AI system that predicts ticket escalation probability. Analyze tickets and return probability as JSON with factors that contribute to escalation risk.';
    }

    /**
     * Extract features for escalation prediction
     */
    protected function extractEscalationFeatures(Ticket $ticket): array
    {
        return [
            'age_hours' => $ticket->created_at->diffInHours(now()),
            'response_count' => $ticket->responses()->count(),
            'priority' => $ticket->priority->name,
            'department' => $ticket->department->name ?? 'general',
            'subject_length' => strlen($ticket->subject),
            'description_length' => strlen($ticket->description),
            'has_angry_words' => $this->containsAngryWords($ticket->description),
            'is_weekend' => now()->isWeekend(),
        ];
    }

    /**
     * Fallback categorization when AI fails
     */
    protected function fallbackCategorization(string $subject, string $description): array
    {
        $text = strtolower($subject.' '.$description);

        // Simple keyword-based fallback
        $department = 'general';
        if (str_contains($text, 'bug') || str_contains($text, 'error')) {
            $department = 'technical';
        } elseif (str_contains($text, 'bill') || str_contains($text, 'payment')) {
            $department = 'billing';
        }

        return [
            'department' => $department,
            'priority' => 'normal',
            'category' => 'question',
            'estimated_resolution_time' => 1440, // 24 hours
            'sentiment' => 'neutral',
            'confidence' => 0.3,
        ];
    }

    /**
     * Find relevant knowledge articles for a ticket
     */
    protected function findRelevantKnowledgeArticles(Ticket $ticket)
    {
        // This will be enhanced with vector search later
        return KnowledgeArticle::published()
            ->where(function ($query) use ($ticket) {
                $query->where('title', 'like', '%'.$ticket->subject.'%')
                    ->orWhere('content', 'like', '%'.$ticket->subject.'%');
            })
            ->limit(3)
            ->get();
    }

    /**
     * Parse response suggestions from AI output
     */
    protected function parseResponseSuggestions(string $content): array
    {
        // Simple parsing - can be enhanced
        $lines = explode("\n", $content);
        $suggestions = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (! empty($line) && ! str_starts_with($line, '#')) {
                $suggestions[] = $line;
            }
        }

        return array_slice($suggestions, 0, 3); // Max 3 suggestions
    }

    /**
     * Check if text contains angry/frustrated words
     */
    protected function containsAngryWords(string $text): bool
    {
        $angryWords = ['angry', 'frustrated', 'terrible', 'awful', 'horrible', 'worst', 'hate', 'disgusted'];
        $text = strtolower($text);

        foreach ($angryWords as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current feature set for tracking
     */
    protected function getCurrentFeatures(): array
    {
        return [
            'gemini_model' => config('services.gemini.default_model'),
            'embedding_model' => config('services.gemini.embedding_model'),
            'feature_version' => '1.0',
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Build escalation prediction prompt
     */
    protected function buildEscalationPrompt(Ticket $ticket, array $features): string
    {
        return "Predict escalation probability for this support ticket:

Ticket Details:
- Subject: {$ticket->subject}
- Priority: {$ticket->priority->name}
- Age: {$features['age_hours']} hours
- Responses: {$features['response_count']}
- Department: {$features['department']}

Content Analysis:
- Has angry language: ".($features['has_angry_words'] ? 'Yes' : 'No')."
- Description length: {$features['description_length']} characters

Return JSON with:
- escalation_probability: (0.0-1.0)
- confidence: (0.0-1.0)
- factors: array of contributing factors";
    }

    /**
     * Get Gemini safety settings
     */
    protected function getSafetySettings(): array
    {
        $settings = config('services.gemini.safety_settings', []);
        $formattedSettings = [];

        foreach ($settings as $category => $threshold) {
            $formattedSettings[] = [
                'category' => $category,
                'threshold' => $threshold,
            ];
        }

        return $formattedSettings;
    }
}

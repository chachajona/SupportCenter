<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'content',
        'sender_type',
        'user_id',
        'metadata',
        'intent',
        'confidence_score',
        'knowledge_articles_referenced',
        'suggested_actions',
        'response_time_ms',
    ];

    protected $casts = [
        'metadata' => 'array',
        'intent' => 'array',
        'confidence_score' => 'float',
        'knowledge_articles_referenced' => 'array',
        'suggested_actions' => 'array',
        'response_time_ms' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Sender types
     */
    const SENDER_TYPES = [
        'user' => 'User',
        'bot' => 'Bot',
        'agent' => 'Human Agent',
        'system' => 'System',
    ];

    /**
     * Get the conversation this message belongs to
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user who sent this message (if applicable)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for user messages
     */
    public function scopeFromUser($query)
    {
        return $query->where('sender_type', 'user');
    }

    /**
     * Scope for bot messages
     */
    public function scopeFromBot($query)
    {
        return $query->where('sender_type', 'bot');
    }

    /**
     * Scope for agent messages
     */
    public function scopeFromAgent($query)
    {
        return $query->where('sender_type', 'agent');
    }

    /**
     * Scope for messages with high confidence
     */
    public function scopeHighConfidence($query, float $threshold = 0.8)
    {
        return $query->where('confidence_score', '>=', $threshold);
    }

    /**
     * Scope for messages with low confidence
     */
    public function scopeLowConfidence($query, float $threshold = 0.6)
    {
        return $query->where('confidence_score', '<', $threshold);
    }

    /**
     * Create a user message
     */
    public static function createUserMessage(int $conversationId, string $content, ?int $userId = null, array $metadata = []): self
    {
        return self::create([
            'conversation_id' => $conversationId,
            'content' => $content,
            'sender_type' => 'user',
            'user_id' => $userId,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a bot message with AI response data
     */
    public static function createBotMessage(
        int $conversationId,
        string $content,
        array $intent = [],
        float $confidence = 0.0,
        array $knowledgeArticles = [],
        array $suggestedActions = [],
        ?int $responseTime = null,
        array $metadata = []
    ): self {
        return self::create([
            'conversation_id' => $conversationId,
            'content' => $content,
            'sender_type' => 'bot',
            'intent' => $intent,
            'confidence_score' => $confidence,
            'knowledge_articles_referenced' => $knowledgeArticles,
            'suggested_actions' => $suggestedActions,
            'response_time_ms' => $responseTime,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create an agent message
     */
    public static function createAgentMessage(int $conversationId, string $content, int $agentId, array $metadata = []): self
    {
        return self::create([
            'conversation_id' => $conversationId,
            'content' => $content,
            'sender_type' => 'agent',
            'user_id' => $agentId,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a system message
     */
    public static function createSystemMessage(int $conversationId, string $content, array $metadata = []): self
    {
        return self::create([
            'conversation_id' => $conversationId,
            'content' => $content,
            'sender_type' => 'system',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Check if this is a user message
     */
    public function isFromUser(): bool
    {
        return $this->sender_type === 'user';
    }

    /**
     * Check if this is a bot message
     */
    public function isFromBot(): bool
    {
        return $this->sender_type === 'bot';
    }

    /**
     * Check if this is an agent message
     */
    public function isFromAgent(): bool
    {
        return $this->sender_type === 'agent';
    }

    /**
     * Check if this is a system message
     */
    public function isFromSystem(): bool
    {
        return $this->sender_type === 'system';
    }

    /**
     * Check if this is a high confidence message
     */
    public function isHighConfidence(float $threshold = 0.8): bool
    {
        return $this->confidence_score >= $threshold;
    }

    /**
     * Check if this is a low confidence message
     */
    public function isLowConfidence(float $threshold = 0.6): bool
    {
        return $this->confidence_score < $threshold;
    }

    /**
     * Get the intent category
     */
    public function getIntentCategory(): ?string
    {
        return $this->intent['category'] ?? null;
    }

    /**
     * Get the urgency level
     */
    public function getUrgencyLevel(): ?string
    {
        return $this->intent['urgency'] ?? null;
    }

    /**
     * Get formatted confidence percentage
     */
    public function getConfidencePercentage(): string
    {
        return number_format($this->confidence_score * 100, 1).'%';
    }

    /**
     * Get formatted sender type
     */
    public function getSenderTypeLabel(): string
    {
        return self::SENDER_TYPES[$this->sender_type] ?? $this->sender_type;
    }

    /**
     * Get sender name for display
     */
    public function getSenderName(): string
    {
        return match ($this->sender_type) {
            'user' => $this->user?->name ?? 'Customer',
            'bot' => 'Support Bot',
            'agent' => $this->user?->name ?? 'Agent',
            'system' => 'System',
            default => 'Unknown',
        };
    }

    /**
     * Get message timestamp for display
     */
    public function getDisplayTime(): string
    {
        return $this->created_at->format('H:i');
    }

    /**
     * Get message timestamp for display with date
     */
    public function getDisplayDateTime(): string
    {
        return $this->created_at->format('M j, H:i');
    }

    /**
     * Check if message has knowledge articles referenced
     */
    public function hasKnowledgeArticles(): bool
    {
        return ! empty($this->knowledge_articles_referenced);
    }

    /**
     * Check if message has suggested actions
     */
    public function hasSuggestedActions(): bool
    {
        return ! empty($this->suggested_actions);
    }

    /**
     * Get response time in seconds
     */
    public function getResponseTimeSeconds(): ?float
    {
        return $this->response_time_ms ? $this->response_time_ms / 1000 : null;
    }

    /**
     * Get formatted response time
     */
    public function getFormattedResponseTime(): ?string
    {
        if (! $this->response_time_ms) {
            return null;
        }

        $seconds = $this->getResponseTimeSeconds();

        if ($seconds < 1) {
            return round($this->response_time_ms).'ms';
        }

        return round($seconds, 1).'s';
    }

    /**
     * Export message for conversation history
     */
    public function toConversationHistory(): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'sender_type' => $this->sender_type,
            'sender_name' => $this->getSenderName(),
            'timestamp' => $this->created_at->toISOString(),
            'confidence' => $this->confidence_score,
            'intent' => $this->intent,
            'knowledge_articles' => $this->knowledge_articles_referenced,
            'suggested_actions' => $this->suggested_actions,
        ];
    }
}

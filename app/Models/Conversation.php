<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'status',
        'channel',
        'started_at',
        'ended_at',
        'escalated_at',
        'escalated_to_ticket_id',
        'escalation_reason',
        'metadata',
        'summary',
        'average_confidence',
        'total_messages',
        'user_satisfaction_rating',
        'feedback',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'escalated_at' => 'datetime',
        'metadata' => 'array',
        'average_confidence' => 'float',
        'total_messages' => 'integer',
        'user_satisfaction_rating' => 'integer',
    ];

    /**
     * Conversation statuses
     */
    const STATUSES = [
        'active' => 'Active',
        'idle' => 'Idle',
        'ended' => 'Ended',
        'escalated' => 'Escalated',
        'abandoned' => 'Abandoned',
    ];

    /**
     * Communication channels
     */
    const CHANNELS = [
        'web_chat' => 'Web Chat',
        'mobile_app' => 'Mobile App',
        'widget' => 'Website Widget',
        'api' => 'API Integration',
    ];

    /**
     * Generate a unique session ID for the conversation
     */
    public static function generateSessionId(): string
    {
        return 'conv_' . Str::random(16);
    }

    /**
     * Get the user associated with this conversation
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all messages in this conversation
     *
     * @return HasMany<ConversationMessage>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at');
    }

    /**
     * Get the ticket this conversation was escalated to
     */
    public function escalatedTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'escalated_to_ticket_id');
    }

    /**
     * Scope for active conversations
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for escalated conversations
     */
    public function scopeEscalated($query)
    {
        return $query->where('status', 'escalated');
    }

    /**
     * Scope for conversations within a date range
     */
    public function scopeWithinDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('started_at', [$startDate, $endDate]);
    }

    /**
     * Start a new conversation
     */
    public static function start(array $data = []): self
    {
        return self::create(array_merge([
            'session_id' => self::generateSessionId(),
            'status' => 'active',
            'channel' => 'web_chat',
            'started_at' => now(),
            'total_messages' => 0,
            'average_confidence' => 0.0,
        ], $data));
    }

    /**
     * Add a message to the conversation
     */
    public function addMessage(string $content, string $senderType, ?int $userId = null, array $metadata = []): ConversationMessage
    {
        /** @var ConversationMessage $message */
        $message = $this->messages()->create([
            'content' => $content,
            'sender_type' => $senderType,
            'user_id' => $userId,
            'metadata' => $metadata,
        ]);

        // Update conversation statistics
        $this->increment('total_messages');
        $this->updateAverageConfidence();
        $this->updateActivity();

        return $message;
    }

    /**
     * End the conversation
     */
    public function end(string $reason = 'user_ended', array $metadata = []): void
    {
        $this->update([
            'status' => 'ended',
            'ended_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], [
                'end_reason' => $reason,
                'end_metadata' => $metadata,
            ]),
        ]);

        $this->generateSummary();
    }

    /**
     * Mark conversation as escalated
     */
    public function escalate(int $ticketId, string $reason = 'user_request'): void
    {
        $this->update([
            'status' => 'escalated',
            'escalated_at' => now(),
            'escalated_to_ticket_id' => $ticketId,
            'escalation_reason' => $reason,
        ]);
    }

    /**
     * Update conversation activity (for idle detection)
     */
    public function updateActivity(): void
    {
        $this->update(['updated_at' => now()]);

        // Reset status to active if it was idle
        if ($this->status === 'idle') {
            $this->update(['status' => 'active']);
        }
    }

    /**
     * Mark conversation as idle
     */
    public function markAsIdle(): void
    {
        if ($this->status === 'active') {
            $this->update(['status' => 'idle']);
        }
    }

    /**
     * Update average confidence from messages
     */
    protected function updateAverageConfidence(): void
    {
        $avgConfidence = $this->messages()
            ->whereNotNull('metadata->confidence')
            ->avg('metadata->confidence');

        if ($avgConfidence !== null) {
            $this->update(['average_confidence' => $avgConfidence]);
        }
    }

    /**
     * Generate conversation summary
     */
    protected function generateSummary(): void
    {
        $messageCount = $this->messages()->count();
        $duration = $this->started_at->diffInMinutes($this->ended_at ?? now());

        $userMessages = $this->messages()->where('sender_type', 'user')->count();
        $botMessages = $this->messages()->where('sender_type', 'bot')->count();

        /** @var ConversationMessage|null $firstMessage */
        $firstMessage = $this->messages()->where('sender_type', 'user')->first();
        $topic = $firstMessage ? substr($firstMessage->content, 0, 100) : 'No topic identified';

        $summary = "Conversation lasted {$duration} minutes with {$messageCount} total messages " .
            "({$userMessages} from user, {$botMessages} from bot). " .
            "Topic: {$topic}" .
            ($this->escalated_at ? " - Escalated to ticket #{$this->escalated_to_ticket_id}" : '');

        $this->update(['summary' => $summary]);
    }

    /**
     * Get conversation duration in minutes
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->ended_at ?? $this->escalated_at ?? now();

        return (int) $this->started_at->diffInMinutes($endTime);
    }

    /**
     * Check if conversation is currently active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if conversation was escalated
     */
    public function wasEscalated(): bool
    {
        return $this->status === 'escalated';
    }

    /**
     * Check if conversation is idle (no activity for over 10 minutes)
     */
    public function isConsideredIdle(): bool
    {
        return $this->updated_at->diffInMinutes(now()) > 10;
    }

    /**
     * Get the last user message
     */
    public function getLastUserMessage(): ?ConversationMessage
    {
        /** @var ConversationMessage|null $message */
        $message = $this->messages()
            ->where('sender_type', 'user')
            ->latest()
            ->first();

        return $message;
    }

    /**
     * Get the last bot message
     */
    public function getLastBotMessage(): ?ConversationMessage
    {
        /** @var ConversationMessage|null $message */
        $message = $this->messages()
            ->where('sender_type', 'bot')
            ->latest()
            ->first();

        return $message;
    }

    /**
     * Rate the conversation (1-5 stars)
     */
    public function rate(int $rating, ?string $feedback = null): void
    {
        $this->update([
            'user_satisfaction_rating' => max(1, min(5, $rating)),
            'feedback' => $feedback,
        ]);
    }

    /**
     * Get conversation satisfaction label
     */
    public function getSatisfactionLabelAttribute(): string
    {
        return match ($this->user_satisfaction_rating) {
            1 => 'Very Dissatisfied',
            2 => 'Dissatisfied',
            3 => 'Neutral',
            4 => 'Satisfied',
            5 => 'Very Satisfied',
            default => 'Not Rated',
        };
    }

    /**
     * Get formatted status
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get formatted channel
     */
    public function getChannelLabelAttribute(): string
    {
        return self::CHANNELS[$this->channel] ?? $this->channel;
    }
}

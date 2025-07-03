<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category',
        'subject',
        'description',
        'priority',
        'status',
        'feature_area',
        'metadata',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Categories for feedback classification
     */
    const CATEGORIES = [
        'bug_report' => 'Bug Report',
        'feature_request' => 'Feature Request',
        'improvement_suggestion' => 'Improvement Suggestion',
        'general_feedback' => 'General Feedback',
        'usability_issue' => 'Usability Issue',
        'performance_issue' => 'Performance Issue',
    ];

    /**
     * Priority levels
     */
    const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    /**
     * Status options
     */
    const STATUSES = [
        'open' => 'Open',
        'under_review' => 'Under Review',
        'in_progress' => 'In Progress',
        'implemented' => 'Implemented',
        'rejected' => 'Rejected',
        'duplicate' => 'Duplicate',
    ];

    /**
     * Feature areas for categorization
     */
    const FEATURE_AREAS = [
        'authentication' => 'Authentication & Security',
        'tickets' => 'Ticket Management',
        'dashboard' => 'Dashboard',
        'knowledge_base' => 'Knowledge Base',
        'user_management' => 'User Management',
        'reports' => 'Reports & Analytics',
        'settings' => 'Settings',
        'api' => 'API',
        'ui_ux' => 'User Interface',
        'performance' => 'Performance',
        'other' => 'Other',
    ];

    /**
     * Get the user who submitted the feedback
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for high priority feedback
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'critical']);
    }

    /**
     * Scope for open feedback
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope for feedback by feature area
     */
    public function scopeByFeatureArea($query, string $area)
    {
        return $query->where('feature_area', $area);
    }

    /**
     * Scope for recent feedback (last 30 days)
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get formatted category name
     */
    public function getCategoryNameAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    /**
     * Get formatted priority name
     */
    public function getPriorityNameAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    /**
     * Get formatted status name
     */
    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get formatted feature area name
     */
    public function getFeatureAreaNameAttribute(): string
    {
        return self::FEATURE_AREAS[$this->feature_area] ?? $this->feature_area;
    }

    /**
     * Check if feedback is urgent
     */
    public function isUrgent(): bool
    {
        return in_array($this->priority, ['high', 'critical']) &&
            in_array($this->category, ['bug_report', 'performance_issue']);
    }

    /**
     * Get feedback age in days
     */
    public function getAgeInDaysAttribute(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Mark feedback as reviewed
     */
    public function markAsReviewed(): void
    {
        $this->update(['status' => 'under_review']);
    }

    /**
     * Mark feedback as implemented
     */
    public function markAsImplemented(): void
    {
        $this->update([
            'status' => 'implemented',
            'metadata' => array_merge($this->metadata ?? [], [
                'implemented_at' => now()->toISOString(),
            ]),
        ]);
    }

    /**
     * Add administrative note to feedback
     */
    public function addAdminNote(string $note, int $adminUserId): void
    {
        $notes = $this->metadata['admin_notes'] ?? [];
        $notes[] = [
            'note' => $note,
            'admin_user_id' => $adminUserId,
            'created_at' => now()->toISOString(),
        ];

        $this->update([
            'metadata' => array_merge($this->metadata ?? [], [
                'admin_notes' => $notes,
            ]),
        ]);
    }

    /**
     * Get trending feedback topics
     */
    public static function getTrendingTopics(int $days = 7): array
    {
        return self::select('subject', 'category', 'feature_area')
            ->selectRaw('COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('subject', 'category', 'feature_area')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get satisfaction metrics
     */
    public static function getSatisfactionMetrics(): array
    {
        $total = self::where('category', 'general_feedback')->count();

        if ($total === 0) {
            return [
                'total_feedback' => 0,
                'satisfaction_rate' => 0,
                'positive_feedback' => 0,
                'negative_feedback' => 0,
            ];
        }

        $positive = self::where('category', 'general_feedback')
            ->whereIn('priority', ['low', 'medium'])
            ->count();

        $negative = self::where('category', 'general_feedback')
            ->whereIn('priority', ['high', 'critical'])
            ->count();

        return [
            'total_feedback' => $total,
            'satisfaction_rate' => round(($positive / $total) * 100, 1),
            'positive_feedback' => $positive,
            'negative_feedback' => $negative,
        ];
    }
}

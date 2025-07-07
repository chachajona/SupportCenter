<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIPrediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'prediction_type',
        'predicted_value',
        'confidence_score',
        'actual_value',
        'feedback_score',
        'model_version',
        'features_used',
    ];

    protected $casts = [
        'predicted_value' => 'array',
        'actual_value' => 'array',
        'features_used' => 'array',
        'confidence_score' => 'decimal:2',
        'feedback_score' => 'integer',
    ];

    /**
     * Get the ticket that this prediction belongs to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Check if this prediction has been validated with actual results.
     */
    public function isValidated(): bool
    {
        return ! is_null($this->actual_value);
    }

    /**
     * Calculate prediction accuracy when actual value is available.
     */
    public function calculateAccuracy(): ?float
    {
        if (! $this->isValidated()) {
            return null;
        }

        // Simple accuracy calculation - can be enhanced based on prediction type
        switch ($this->prediction_type) {
            case 'category':
            case 'priority':
                return $this->predicted_value['department'] === $this->actual_value['department'] ? 1.0 : 0.0;

            case 'escalation':
                $predicted = (float) $this->predicted_value['probability'];
                $actual = (bool) $this->actual_value['escalated'];
                $threshold = 0.5;

                return ($predicted > $threshold) === $actual ? 1.0 : 0.0;

            default:
                return null;
        }
    }

    /**
     * Store feedback for this prediction.
     */
    public function recordFeedback(int $score, ?array $actualValue = null): void
    {
        $this->update([
            'feedback_score' => $score,
            'actual_value' => $actualValue ?? $this->actual_value,
        ]);
    }

    /**
     * Get predictions by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('prediction_type', $type);
    }

    /**
     * Get high confidence predictions.
     */
    public function scopeHighConfidence($query, float $threshold = 0.8)
    {
        return $query->where('confidence_score', '>=', $threshold);
    }

    /**
     * Get validated predictions.
     */
    public function scopeValidated($query)
    {
        return $query->whereNotNull('actual_value');
    }

    /**
     * Get recent predictions.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}

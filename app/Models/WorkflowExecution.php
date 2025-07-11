<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int|null $workflow_id
 * @property int|null $workflow_rule_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $status
 * @property array<array-key, mixed> $execution_data
 * @property array<array-key, mixed>|null $execution_result
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int|null $triggered_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WorkflowAction> $actions
 * @property-read int|null $actions_count
 * @property-read Model|\Eloquent $entity
 * @property-read int|null $execution_time
 * @property-read \App\Models\User|null $triggeredBy
 * @property-read \App\Models\Workflow|null $workflow
 * @property-read \App\Models\WorkflowRule|null $workflowRule
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution byStatus(string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution completed()
 * @method static \Database\Factories\WorkflowExecutionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution failed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution forEntity(string $entityType, int $entityId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution forEntityType(string $entityType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution recent(int $days = 7)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution running()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution whereEntityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution whereExecutionData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution whereExecutionResult($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution whereTriggeredBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution whereWorkflowId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution whereWorkflowRuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowExecution withExecutionTime()
 *
 * @mixin \Eloquent
 */
final class WorkflowExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'workflow_rule_id',
        'entity_type',
        'entity_id',
        'status',
        'execution_data',
        'execution_result',
        'error_message',
        'started_at',
        'completed_at',
        'triggered_by',
    ];

    protected $casts = [
        'execution_data' => 'array',
        'execution_result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the workflow that this execution belongs to.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the workflow rule that this execution belongs to.
     */
    public function workflowRule(): BelongsTo
    {
        return $this->belongsTo(WorkflowRule::class);
    }

    /**
     * Get the user who triggered this execution.
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * Get all actions for this execution.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class);
    }

    /**
     * Get the entity that this execution was run against.
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the execution duration in seconds.
     */
    public function getExecutionTimeAttribute(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->completed_at);
    }

    /**
     * Check if the execution is currently running.
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if the execution completed successfully.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the execution failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the execution was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Mark the execution as started.
     */
    public function markAsStarted(): self
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark the execution as completed.
     */
    public function markAsCompleted(array $result = []): self
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'execution_result' => $result,
        ]);

        return $this;
    }

    /**
     * Mark the execution as failed.
     */
    public function markAsFailed(string $errorMessage): self
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);

        return $this;
    }

    /**
     * Mark the execution as cancelled.
     */
    public function markAsCancelled(): self
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Get the summary of actions executed.
     */
    public function getActionSummary(): array
    {
        $actions = $this->actions;

        return [
            'total_actions' => $actions->count(),
            'completed_actions' => $actions->where('status', 'completed')->count(),
            'failed_actions' => $actions->where('status', 'failed')->count(),
            'skipped_actions' => $actions->where('status', 'skipped')->count(),
            'pending_actions' => $actions->where('status', 'pending')->count(),
        ];
    }

    /**
     * Get the execution context data.
     */
    public function getContextData(): array
    {
        return [
            'execution_id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'workflow_rule_id' => $this->workflow_rule_id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'triggered_by' => $this->triggered_by,
            'started_at' => $this->started_at,
            'execution_data' => $this->execution_data,
        ];
    }

    /**
     * Scope to get executions by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get running executions.
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope to get completed executions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get failed executions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get executions by entity type.
     */
    public function scopeForEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope to get executions for a specific entity.
     */
    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query->where('entity_type', $entityType)->where('entity_id', $entityId);
    }

    /**
     * Scope to get recent executions.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to get executions with execution time.
     */
    public function scopeWithExecutionTime($query)
    {
        return $query->whereNotNull('started_at')->whereNotNull('completed_at');
    }
}

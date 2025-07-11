<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workflow_execution_id
 * @property string $action_type
 * @property array<array-key, mixed> $action_data
 * @property string $status
 * @property array<array-key, mixed>|null $result
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int|null $execution_time
 * @property-read \App\Models\WorkflowExecution $workflowExecution
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction byStatus(string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction byType(string $actionType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction completed()
 * @method static \Database\Factories\WorkflowActionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction failed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction running()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction whereActionData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction whereActionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction whereResult($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction whereWorkflowExecutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowAction withExecutionTime()
 *
 * @mixin \Eloquent
 */
final class WorkflowAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_execution_id',
        'action_type',
        'action_data',
        'status',
        'result',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'action_data' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the workflow execution that this action belongs to.
     */
    public function workflowExecution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class);
    }

    /**
     * Get the action duration in seconds.
     */
    public function getExecutionTimeAttribute(): ?float
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }

    /**
     * Check if the action is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the action is currently running.
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if the action completed successfully.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the action failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the action was skipped.
     */
    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    /**
     * Mark the action as started.
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
     * Mark the action as completed.
     */
    public function markAsCompleted(array $result = []): self
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'result' => $result,
        ]);

        return $this;
    }

    /**
     * Mark the action as failed.
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
     * Mark the action as skipped.
     */
    public function markAsSkipped(string $reason = ''): self
    {
        $this->update([
            'status' => 'skipped',
            'completed_at' => now(),
            'result' => ['reason' => $reason],
        ]);

        return $this;
    }

    /**
     * Get the action configuration.
     */
    public function getActionConfig(): array
    {
        return $this->action_data;
    }

    /**
     * Get a specific action configuration value.
     */
    public function getActionConfigValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->action_data, $key, $default);
    }

    /**
     * Get the action result.
     */
    public function getResult(): array
    {
        return $this->result ?? [];
    }

    /**
     * Get a specific result value.
     */
    public function getResultValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->result, $key, $default);
    }

    /**
     * Scope to get actions by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get actions by type.
     */
    public function scopeByType($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    /**
     * Scope to get pending actions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get running actions.
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope to get completed actions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get failed actions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get actions with execution time.
     */
    public function scopeWithExecutionTime($query)
    {
        return $query->whereNotNull('started_at')->whereNotNull('completed_at');
    }
}

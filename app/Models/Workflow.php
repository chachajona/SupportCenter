<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $trigger_type
 * @property array<array-key, mixed>|null $trigger_conditions
 * @property array<array-key, mixed> $workflow_data
 * @property bool $is_active
 * @property int $created_by
 * @property int $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WorkflowExecution> $executions
 * @property-read int|null $executions_count
 * @property-read array $connections
 * @property-read array $nodes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WorkflowExecution> $recentExecutions
 * @property-read int|null $recent_executions_count
 * @property-read \App\Models\User $updater
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow byTriggerType(string $triggerType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow createdBy(int $userId)
 * @method static \Database\Factories\WorkflowFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow whereTriggerConditions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow whereTriggerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workflow whereWorkflowData($value)
 *
 * @mixin \Eloquent
 */
final class Workflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'trigger_type',
        'trigger_conditions',
        'workflow_data',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'trigger_conditions' => 'array',
        'workflow_data' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user who created this workflow.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this workflow.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all executions for this workflow.
     */
    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    /**
     * Get recent executions for this workflow.
     */
    public function recentExecutions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class)->latest()->limit(10);
    }

    /**
     * Get workflow nodes from the workflow_data.
     */
    public function getNodesAttribute(): array
    {
        return $this->workflow_data['nodes'] ?? [];
    }

    /**
     * Get workflow connections from the workflow_data.
     */
    public function getConnectionsAttribute(): array
    {
        return $this->workflow_data['connections'] ?? [];
    }

    /**
     * Check if this workflow can be triggered by the given conditions.
     */
    public function canTrigger(array $conditions): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (empty($this->trigger_conditions)) {
            return true;
        }

        return $this->matchesConditions($conditions);
    }

    /**
     * Check if the given conditions match this workflow's trigger conditions.
     */
    private function matchesConditions(array $conditions): bool
    {
        foreach ($this->trigger_conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? '';

            if (! isset($conditions[$field])) {
                return false;
            }

            $actualValue = $conditions[$field];

            if (! $this->evaluateCondition($actualValue, $operator, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition.
     */
    private function evaluateCondition(mixed $actualValue, string $operator, mixed $expectedValue): bool
    {
        return match ($operator) {
            '=' => $actualValue == $expectedValue,
            '!=' => $actualValue != $expectedValue,
            '>' => $actualValue > $expectedValue,
            '<' => $actualValue < $expectedValue,
            '>=' => $actualValue >= $expectedValue,
            '<=' => $actualValue <= $expectedValue,
            'contains' => str_contains((string) $actualValue, (string) $expectedValue),
            'starts_with' => str_starts_with((string) $actualValue, (string) $expectedValue),
            'ends_with' => str_ends_with((string) $actualValue, (string) $expectedValue),
            'in' => in_array($actualValue, (array) $expectedValue),
            'not_in' => ! in_array($actualValue, (array) $expectedValue),
            default => false,
        };
    }

    /**
     * Get execution statistics for this workflow.
     */
    public function getExecutionStats(): array
    {
        $executions = $this->executions();

        return [
            'total_executions' => $executions->count(),
            'successful_executions' => $executions->where('status', 'completed')->count(),
            'failed_executions' => $executions->where('status', 'failed')->count(),
            'success_rate' => $executions->count() > 0
                ? ($executions->where('status', 'completed')->count() / $executions->count()) * 100
                : 0,
            'average_execution_time' => $executions->whereNotNull('completed_at')->avg('execution_time'),
        ];
    }

    /**
     * Scope to get active workflows.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get workflows by trigger type.
     */
    public function scopeByTriggerType($query, string $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }

    /**
     * Scope to get workflows created by user.
     */
    public function scopeCreatedBy($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }
}

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
 * @property string $entity_type
 * @property array<array-key, mixed> $conditions
 * @property array<array-key, mixed> $actions
 * @property array<array-key, mixed>|null $schedule
 * @property bool $is_active
 * @property int $priority
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WorkflowExecution> $executions
 * @property-read int|null $executions_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule byPriority(string $order = 'desc')
 * @method static \Database\Factories\WorkflowRuleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule forEntityType(string $entityType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule scheduled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule whereActions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule whereConditions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule whereEntityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule whereSchedule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowRule whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class WorkflowRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'entity_type',
        'conditions',
        'actions',
        'schedule',
        'is_active',
        'priority',
        'execution_limit',
        'execution_count',
        'last_executed_at',
        'created_by',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'schedule' => 'array',
        'is_active' => 'boolean',
        'last_executed_at' => 'datetime',
    ];

    /**
     * Get the user who created this rule.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all executions for this rule.
     */
    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    /**
     * Check if this rule matches the given entity.
     */
    public function matches(Model $entity): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Check entity type
        if ($this->entity_type !== $this->getEntityType($entity)) {
            return false;
        }

        // Check conditions
        return $this->evaluateConditions($entity);
    }

    /**
     * Get the entity type for a given model.
     */
    private function getEntityType(Model $entity): string
    {
        return match (get_class($entity)) {
            Ticket::class => 'ticket',
            User::class => 'user',
            Department::class => 'department',
            KnowledgeArticle::class => 'knowledge_article',
            default => 'unknown',
        };
    }

    /**
     * Evaluate all conditions against the entity.
     */
    private function evaluateConditions(Model $entity): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        $logicalOperator = $this->conditions['operator'] ?? 'and';
        $rules = $this->conditions['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        $results = [];
        foreach ($rules as $rule) {
            $results[] = $this->evaluateRule($entity, $rule);
        }

        return match ($logicalOperator) {
            'and' => ! in_array(false, $results),
            'or' => in_array(true, $results),
            default => true,
        };
    }

    /**
     * Evaluate a single rule against the entity.
     */
    private function evaluateRule(Model $entity, array $rule): bool
    {
        $field = $rule['field'] ?? '';
        $operator = $rule['operator'] ?? '=';
        $value = $rule['value'] ?? '';

        // Handle nested properties (e.g., 'user.department.name')
        $actualValue = $this->getNestedValue($entity, $field);

        return $this->evaluateCondition($actualValue, $operator, $value);
    }

    /**
     * Get a nested value from an entity using dot notation.
     */
    private function getNestedValue(Model $entity, string $field): mixed
    {
        $parts = explode('.', $field);
        $value = $entity;

        foreach ($parts as $part) {
            if (is_object($value) && isset($value->$part)) {
                $value = $value->$part;
            } elseif (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Evaluate a single condition (reused from Workflow model).
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
            'is_null' => is_null($actualValue),
            'is_not_null' => ! is_null($actualValue),
            'is_empty' => empty($actualValue),
            'is_not_empty' => ! empty($actualValue),
            default => false,
        };
    }

    /**
     * Check if this rule has a schedule.
     */
    public function hasSchedule(): bool
    {
        return ! empty($this->schedule);
    }

    /**
     * Check if this rule should run now based on its schedule.
     */
    public function shouldRunNow(): bool
    {
        // Check execution limits first
        if ($this->execution_limit && $this->execution_count >= $this->execution_limit) {
            return false;
        }

        if (! $this->hasSchedule()) {
            return true;
        }

        $now = now();
        $schedule = $this->schedule;

        // Check time-based conditions
        if (isset($schedule['time'])) {
            $scheduledTime = $schedule['time'];
            $currentTime = $now->format('H:i');

            if ($currentTime !== $scheduledTime) {
                return false;
            }
        }

        // Check day-based conditions
        if (isset($schedule['days'])) {
            $scheduledDays = $schedule['days'];
            $currentDay = strtolower($now->format('l'));

            if (! in_array($currentDay, $scheduledDays)) {
                return false;
            }
        }

        // Check frequency-based conditions
        if (isset($schedule['frequency'])) {
            $frequency = $schedule['frequency'];
            $lastExecution = $this->executions()->latest()->first();

            if ($lastExecution) {
                $timeSinceLastExecution = $now->diffInMinutes($lastExecution->started_at);

                $requiredInterval = match ($frequency) {
                    'hourly' => 60,
                    'daily' => 24 * 60,
                    'weekly' => 7 * 24 * 60,
                    'monthly' => 30 * 24 * 60,
                    default => 60,
                };

                if ($timeSinceLastExecution < $requiredInterval) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get execution statistics for this rule.
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
            'last_execution' => $executions->latest()->first()?->started_at,
        ];
    }

    /**
     * Scope to get active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get rules by entity type.
     */
    public function scopeForEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope to get rules by priority.
     */
    public function scopeByPriority($query, string $order = 'desc')
    {
        return $query->orderBy('priority', $order);
    }

    /**
     * Scope to get scheduled rules.
     */
    public function scopeScheduled($query)
    {
        return $query->whereNotNull('schedule');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $category
 * @property array<array-key, mixed> $template_data
 * @property bool $is_system_template
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read array $connections
 * @property-read array $nodes
 * @property-read array $trigger_config
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowTemplate byCategory(string $category)
 * @method static \Database\Factories\WorkflowTemplateFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowTemplate systemTemplates()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowTemplate userTemplates()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowTemplate whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowTemplate whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowTemplate whereIsSystemTemplate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowTemplate whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowTemplate whereTemplateData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkflowTemplate whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class WorkflowTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'template_data',
        'is_system_template',
    ];

    protected $casts = [
        'template_data' => 'array',
        'is_system_template' => 'boolean',
    ];

    /**
     * Get the template nodes.
     */
    public function getNodesAttribute(): array
    {
        return $this->template_data['nodes'] ?? [];
    }

    /**
     * Get the template connections.
     */
    public function getConnectionsAttribute(): array
    {
        return $this->template_data['connections'] ?? [];
    }

    /**
     * Get the template trigger configuration.
     */
    public function getTriggerConfigAttribute(): array
    {
        return $this->template_data['trigger'] ?? [];
    }

    /**
     * Create a workflow from this template.
     */
    public function createWorkflow(string $name, ?string $description = null, ?int $createdBy = null): Workflow
    {
        return Workflow::create([
            'name' => $name,
            'description' => $description ?? $this->description,
            'trigger_type' => $this->trigger_config['type'] ?? 'manual',
            'trigger_conditions' => $this->trigger_config['conditions'] ?? [],
            'workflow_data' => $this->template_data,
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);
    }

    /**
     * Update template data.
     */
    public function updateTemplateData(array $data): self
    {
        $this->update([
            'template_data' => array_merge($this->template_data, $data),
        ]);

        return $this;
    }

    /**
     * Check if this is a system template.
     */
    public function isSystemTemplate(): bool
    {
        return $this->is_system_template;
    }

    /**
     * Scope to get templates by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get system templates.
     */
    public function scopeSystemTemplates($query)
    {
        return $query->where('is_system_template', true);
    }

    /**
     * Scope to get user templates.
     */
    public function scopeUserTemplates($query)
    {
        return $query->where('is_system_template', false);
    }
}

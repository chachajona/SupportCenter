<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $department_id
 * @property int $sort_order
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Department|null $department
 * @property-read \Illuminate\Database\Eloquent\Collection<int, KnowledgeArticle> $articles
 * @property-read int $articles_count
 * @property-read int $published_articles_count
 */
final class KnowledgeCategory extends Model
{
    /** @use HasFactory<\Database\Factories\KnowledgeCategoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'department_id',
        'sort_order',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'department_id' => 'integer',
    ];

    /**
     * Get the department this category belongs to.
     *
     * @return BelongsTo<Department, KnowledgeCategory>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get all articles in this category.
     *
     * @return HasMany<KnowledgeArticle, KnowledgeCategory>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgeArticle::class, 'category_id');
    }

    /**
     * Get published articles count.
     */
    public function getPublishedArticlesCountAttribute(): int
    {
        return $this->articles()->where('status', 'published')->count();
    }

    /**
     * Scope to only active categories.
     *
     * @param  Builder<KnowledgeCategory>  $query
     * @return Builder<KnowledgeCategory>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by department.
     *
     * @param  Builder<KnowledgeCategory>  $query
     * @return Builder<KnowledgeCategory>
     */
    public function scopeForDepartment(Builder $query, ?int $departmentId): Builder
    {
        /** @var Builder<KnowledgeCategory> $query */
        if ($departmentId === null) {
            return $query->whereNull('department_id');
        }

        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope ordered by sort order.
     *
     * @param  Builder<KnowledgeCategory>  $query
     * @return Builder<KnowledgeCategory>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        /** @var Builder<KnowledgeCategory> $query */
        return $query->orderBy('sort_order')->orderBy('name');
    }
}

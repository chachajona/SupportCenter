<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $title
 * @property string $content
 * @property string|null $summary
 * @property int $category_id
 * @property int|null $department_id
 * @property int $author_id
 * @property string $status
 * @property bool $is_public
 * @property int $view_count
 * @property array<string>|null $tags
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read KnowledgeCategory $category
 * @property-read Department|null $department
 * @property-read User $author
 */
final class KnowledgeArticle extends Model
{
    /** @use HasFactory<\Database\Factories\KnowledgeArticleFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'content',
        'summary',
        'category_id',
        'department_id',
        'author_id',
        'status',
        'is_public',
        'tags',
        'published_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_public' => 'boolean',
        'view_count' => 'integer',
        'tags' => 'array',
        'published_at' => 'datetime',
        'category_id' => 'integer',
        'department_id' => 'integer',
        'author_id' => 'integer',
    ];

    /**
     * Get the category this article belongs to.
     *
     * @return BelongsTo<KnowledgeCategory, KnowledgeArticle>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCategory::class, 'category_id');
    }

    /**
     * Get the department this article belongs to.
     *
     * @return BelongsTo<Department, KnowledgeArticle>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the author of this article.
     *
     * @return BelongsTo<User, KnowledgeArticle>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Increment view count.
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Check if article is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }

    /**
     * Check if article is draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Publish the article.
     */
    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    /**
     * Archive the article.
     */
    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    /**
     * Scope to only published articles.
     *
     * @param  Builder<KnowledgeArticle>  $query
     * @return Builder<KnowledgeArticle>
     */
    public function scopePublished(Builder $query): Builder
    {
        /** @var Builder<KnowledgeArticle> $query */
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    /**
     * Scope to only public articles.
     *
     * @param  Builder<KnowledgeArticle>  $query
     * @return Builder<KnowledgeArticle>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope by department.
     *
     * @param  Builder<KnowledgeArticle>  $query
     * @return Builder<KnowledgeArticle>
     */
    public function scopeForDepartment(Builder $query, ?int $departmentId): Builder
    {
        if ($departmentId === null) {
            return $query->where('is_public', true);
        }

        return $query->where(function (Builder $q) use ($departmentId) {
            $q->where('department_id', $departmentId)
                ->orWhere('is_public', true);
        });
    }

    /**
     * Scope to search articles.
     *
     * @param  Builder<KnowledgeArticle>  $query
     * @return Builder<KnowledgeArticle>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'LIKE', "%{$term}%")
                ->orWhere('content', 'LIKE', "%{$term}%")
                ->orWhere('summary', 'LIKE', "%{$term}%")
                ->orWhereJsonContains('tags', $term);
        });
    }

    /**
     * Scope for full-text search.
     *
     * @param  Builder<KnowledgeArticle>  $query
     * @return Builder<KnowledgeArticle>
     */
    public function scopeFullTextSearch(Builder $query, string $term): Builder
    {
        /** @var Builder<KnowledgeArticle> $query */
        return $query->whereRaw(
            'MATCH(title, content, summary) AGAINST(? IN BOOLEAN MODE)',
            [$term]
        );
    }

    /**
     * Scope by category.
     *
     * @param  Builder<KnowledgeArticle>  $query
     * @return Builder<KnowledgeArticle>
     */
    public function scopeInCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope by tag.
     *
     * @param  Builder<KnowledgeArticle>  $query
     * @return Builder<KnowledgeArticle>
     */
    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        /** @var Builder<KnowledgeArticle> $query */
        return $query->whereJsonContains('tags', $tag);
    }

    /**
     * Scope ordered by popularity.
     *
     * @param  Builder<KnowledgeArticle>  $query
     * @return Builder<KnowledgeArticle>
     */
    public function scopePopular(Builder $query): Builder
    {
        /** @var Builder<KnowledgeArticle> $query */
        return $query->orderBy('view_count', 'desc');
    }

    /**
     * Scope ordered by recent.
     *
     * @param  Builder<KnowledgeArticle>  $query
     * @return Builder<KnowledgeArticle>
     */
    public function scopeRecent(Builder $query): Builder
    {
        /** @var Builder<KnowledgeArticle> $query */
        return $query->orderBy('published_at', 'desc');
    }
}

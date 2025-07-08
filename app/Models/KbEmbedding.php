<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class KbEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'embedding_vector',
        'content_hash',
        'model_used',
        'vector_dimension',
    ];

    protected $casts = [
        'embedding_vector' => 'array',
        'vector_dimension' => 'integer',
    ];

    /**
     * Small epsilon value for floating-point comparisons.
     */
    private const EPSILON = 1e-9;

    /**
     * Get the knowledge article that this embedding belongs to.
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'article_id');
    }

    /**
     * Calculate cosine similarity between this embedding and another vector.
     */
    public function cosineSimilarity(array $otherVector): float
    {
        $vector1 = $this->embedding_vector;
        $vector2 = $otherVector;

        if (count($vector1) !== count($vector2)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitude1 = 0.0;
        $magnitude2 = 0.0;

        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] * $vector1[$i];
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        // Handle floating-point precision by comparing against a small epsilon.
        if (abs($magnitude1) < self::EPSILON || abs($magnitude2) < self::EPSILON) {
            return 0.0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    /**
     * Find similar embeddings using cosine similarity.
     */
    public static function findSimilar(array $queryVector, int $limit = 5, float $threshold = 0.0): Collection
    {
        /**
         * To avoid loading all embeddings into memory, iterate through them in manageable chunks
         * and keep track of only the top-N most similar results.
         */
        $topSimilarities = [];

        static::chunkById(1000, function ($embeddings) use (&$topSimilarities, $queryVector, $threshold, $limit) {
            foreach ($embeddings as $embedding) {
                $similarity = $embedding->cosineSimilarity($queryVector);

                if ($similarity < $threshold) {
                    continue;
                }

                $topSimilarities[] = [
                    'embedding' => $embedding,
                    'similarity' => $similarity,
                    'article' => $embedding->article,
                ];

                // Keep the array size in check by trimming to roughly double the limit.
                if (count($topSimilarities) > $limit * 2) {
                    usort($topSimilarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
                    $topSimilarities = array_slice($topSimilarities, 0, $limit);
                }
            }
        });

        // Final sort and trim to the requested limit.
        usort($topSimilarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return collect($topSimilarities)->take($limit);
    }

    /**
     * Generate content hash for change detection.
     */
    public static function generateContentHash(string $content): string
    {
        return hash('sha256', trim($content));
    }

    /**
     * Check if content has changed since last embedding.
     */
    public function hasContentChanged(string $currentContent): bool
    {
        $currentHash = static::generateContentHash($currentContent);

        return $this->content_hash !== $currentHash;
    }

    /**
     * Update embedding with new content.
     */
    public function updateEmbedding(array $newVector, string $newContent, ?string $model = null): void
    {
        $this->update([
            'embedding_vector' => $newVector,
            'content_hash' => static::generateContentHash($newContent),
            'model_used' => $model ?? $this->model_used,
            'vector_dimension' => count($newVector),
        ]);
    }

    /**
     * Get embeddings that need updating (content changed).
     */
    public function scopeNeedsUpdate($query)
    {
        return $query->whereHas('article', function ($articleQuery) {
            $articleQuery->where('updated_at', '>', function ($subQuery) {
                $subQuery->select('updated_at')
                    ->from('kb_embeddings')
                    ->whereColumn('article_id', 'knowledge_articles.id');
            });
        });
    }

    /**
     * Get embeddings by model.
     */
    public function scopeByModel($query, string $model)
    {
        return $query->where('model_used', $model);
    }

    /**
     * Get recent embeddings.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('updated_at', '>=', now()->subDays($days));
    }

    /**
     * Get statistics about embeddings.
     */
    public static function getStatistics(): array
    {
        $total = static::count();
        $byModel = static::selectRaw('model_used, COUNT(*) as count')
            ->groupBy('model_used')
            ->pluck('count', 'model_used')
            ->toArray();

        $needsUpdate = static::needsUpdate()->count();

        return [
            'total_embeddings' => $total,
            'by_model' => $byModel,
            'needs_update' => $needsUpdate,
            'coverage_percentage' => $total > 0 ? round(($total / KnowledgeArticle::count()) * 100, 2) : 0,
        ];
    }
}

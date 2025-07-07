<?php

namespace App\Console\Commands;

use App\Models\KbEmbedding;
use App\Models\KnowledgeArticle;
use App\Services\AI\MachineLearningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateKnowledgeEmbeddings extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:generate-embeddings
                           {--dry-run : Show what would be processed without making changes}
                           {--force : Force regeneration of existing embeddings}
                           {--batch-size=10 : Number of articles to process in each batch}
                           {--article-id= : Process specific article ID only}';

    /**
     * The console command description.
     */
    protected $description = 'Generate vector embeddings for knowledge base articles for semantic search';

    protected MachineLearningService $mlService;

    public function __construct(MachineLearningService $mlService)
    {
        parent::__construct();
        $this->mlService = $mlService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🤖 Knowledge Base Embedding Generation');
        $this->newLine();

        // Check if Gemini API is configured
        if (! config('services.gemini.api_key')) {
            $this->error('❌ Gemini API key not configured. Please set GEMINI_API_KEY in your environment.');

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $batchSize = (int) $this->option('batch-size');
        $articleId = $this->option('article-id');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get articles to process
        $articles = $this->getArticlesToProcess($articleId, $force);

        if ($articles->isEmpty()) {
            $this->info('✅ No articles need embedding generation');
            $this->showStatistics();

            return self::SUCCESS;
        }

        $this->info("📊 Found {$articles->count()} articles to process");

        if (! $dryRun) {
            if (! $this->confirm('Continue with embedding generation?')) {
                $this->info('Operation cancelled');

                return self::SUCCESS;
            }
        }

        $this->newLine();

        // Process articles in batches
        $processed = 0;
        $errors = 0;
        $skipped = 0;

        $progressBar = $this->output->createProgressBar($articles->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        foreach ($articles->chunk($batchSize) as $batch) {
            foreach ($batch as $article) {
                try {
                    $result = $this->processArticle($article, $dryRun, $force);

                    if ($result === 'processed') {
                        $processed++;
                    } elseif ($result === 'skipped') {
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Embedding generation failed for article', [
                        'article_id' => $article->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $progressBar->advance();

                // Small delay to prevent API rate limiting
                if (! $dryRun) {
                    usleep(100000); // 100ms delay
                }
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Show results
        $this->showResults($processed, $errors, $skipped, $dryRun);
        $this->showStatistics();

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Get articles that need embedding processing
     */
    protected function getArticlesToProcess(?string $articleId, bool $force)
    {
        $query = KnowledgeArticle::published();

        if ($articleId) {
            return $query->where('id', $articleId)->get();
        }

        if ($force) {
            return $query->get();
        }

        // Get articles without embeddings or with outdated embeddings
        return $query->whereDoesntHave('embeddings')
            ->orWhereHas('embeddings', function ($embeddingQuery) {
                $embeddingQuery->where('updated_at', '<', DB::raw('knowledge_articles.updated_at'));
            })
            ->get();
    }

    /**
     * Process a single article
     */
    protected function processArticle(KnowledgeArticle $article, bool $dryRun, bool $force): string
    {
        $content = $this->getArticleContent($article);
        $contentHash = KbEmbedding::generateContentHash($content);

        // Check if embedding exists and is current
        $existingEmbedding = $article->embeddings()->first();

        if ($existingEmbedding && ! $force) {
            if ($existingEmbedding->content_hash === $contentHash) {
                return 'skipped'; // Content hasn't changed
            }
        }

        if ($dryRun) {
            $this->line("📝 Would generate embedding for: {$article->title}");

            return 'processed';
        }

        // Generate embedding
        $vector = $this->mlService->generateEmbeddings($content);

        if (empty($vector)) {
            throw new \Exception('Failed to generate embedding vector');
        }

        // Store or update embedding
        if ($existingEmbedding) {
            $existingEmbedding->updateEmbedding($vector, $content, config('services.gemini.embedding_model'));
        } else {
            KbEmbedding::create([
                'article_id' => $article->id,
                'embedding_vector' => $vector,
                'content_hash' => $contentHash,
                'model_used' => config('services.gemini.embedding_model'),
                'vector_dimension' => count($vector),
            ]);
        }

        return 'processed';
    }

    /**
     * Get article content for embedding
     */
    protected function getArticleContent(KnowledgeArticle $article): string
    {
        return $article->title."\n\n".strip_tags($article->content);
    }

    /**
     * Show processing results
     */
    protected function showResults(int $processed, int $errors, int $skipped, bool $dryRun): void
    {
        $this->info('📈 Processing Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $processed],
                ['Skipped (unchanged)', $skipped],
                ['Errors', $errors],
            ]
        );

        if ($errors > 0) {
            $this->error("⚠️  {$errors} articles failed to process. Check logs for details.");
        }

        if (! $dryRun && $processed > 0) {
            $this->info("✅ Successfully generated embeddings for {$processed} articles");
        }
    }

    /**
     * Show embedding statistics
     */
    protected function showStatistics(): void
    {
        $stats = KbEmbedding::getStatistics();

        $this->newLine();
        $this->info('📊 Embedding Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Articles', KnowledgeArticle::published()->count()],
                ['Articles with Embeddings', $stats['total_embeddings']],
                ['Coverage Percentage', $stats['coverage_percentage'].'%'],
                ['Articles Needing Updates', $stats['needs_update']],
            ]
        );

        if (! empty($stats['by_model'])) {
            $this->newLine();
            $this->info('🤖 Embeddings by Model:');
            $modelData = [];
            foreach ($stats['by_model'] as $model => $count) {
                $modelData[] = [$model, $count];
            }
            $this->table(['Model', 'Count'], $modelData);
        }
    }
}

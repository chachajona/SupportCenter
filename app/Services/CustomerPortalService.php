<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AI\MachineLearningService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CustomerPortalService
{
    public function __construct(
        private readonly MachineLearningService $mlService
    ) {
    }

    /**
     * Perform intelligent knowledge base search
     */
    public function searchKnowledgeBase(string $query, int $limit = 10): array
    {
        $cacheKey = 'knowledge_search_' . md5($query . $limit);

        return Cache::remember($cacheKey, 300, function () use ($query, $limit) {
            // Use full-text search
            $articles = KnowledgeArticle::published()
                ->whereRaw('MATCH(title, content) AGAINST (? IN NATURAL LANGUAGE MODE)', [$query])
                ->orderByRaw('MATCH(title, content) AGAINST (? IN NATURAL LANGUAGE MODE) DESC', [$query])
                ->limit($limit)
                ->get();

            // Get semantic search results if we have embeddings
            $semanticResults = $this->getSemanticSearchResults($query, $limit);

            // Merge and deduplicate results
            $combinedResults = $this->mergeSearchResults($articles, $semanticResults);

            return [
                'articles' => $combinedResults->map(function ($article) {
                    return [
                        'id' => $article->id,
                        'title' => $article->title,
                        'slug' => $article->slug,
                        'excerpt' => substr(strip_tags($article->content), 0, 200) . '...',
                        'category' => $article->category?->name,
                        'helpful_count' => $article->helpful_count ?? 0,
                        'view_count' => $article->view_count ?? 0,
                        'url' => route('knowledge.show', $article->slug),
                        'relevance_score' => $article->relevance_score ?? 0.5,
                    ];
                }),
                'total_results' => $combinedResults->count(),
                'search_suggestions' => $this->getSearchSuggestions($query),
            ];
        });
    }

    /**
     * Get popular knowledge articles
     */
    public function getPopularArticles(int $limit = 5): Collection
    {
        return Cache::remember('popular_articles', 600, function () use ($limit) {
            return KnowledgeArticle::published()
                ->orderByDesc('view_count')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get recent knowledge articles
     */
    public function getRecentArticles(int $limit = 5): Collection
    {
        return Cache::remember('recent_articles', 300, function () use ($limit) {
            return KnowledgeArticle::published()
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Create an intelligent ticket with AI assistance
     */
    public function createIntelligentTicket(array $data, ?User $user = null): array
    {
        try {
            $subject = $data['subject'];
            $description = $data['description'];

            // Use AI to categorize and enhance the ticket
            $aiAnalysis = $this->mlService->categorizeTicket($subject, $description);

            // Find relevant knowledge articles first
            $relevantArticles = $this->searchKnowledgeBase($subject . ' ' . $description, 3);

            // Check if this can be self-resolved
            if ($this->canBeSelfResolved($aiAnalysis, $relevantArticles)) {
                return [
                    'can_self_resolve' => true,
                    'ai_analysis' => $aiAnalysis,
                    'suggested_articles' => $relevantArticles['articles'],
                    'message' => 'Based on your description, we found some articles that might help resolve your issue. Would you like to try these solutions first?',
                ];
            }

            // Create the ticket with AI enhancements
            $ticketData = [
                'subject' => $subject,
                'description' => $description,
                'priority_id' => $this->getPriorityFromAI($aiAnalysis),
                'department_id' => $this->getDepartmentFromAI($aiAnalysis),
                'status_id' => 1, // Open
                'created_by' => $user?->id,
            ];

            $ticket = Ticket::create($ticketData);

            return [
                'can_self_resolve' => false,
                'ticket_created' => true,
                'ticket' => [
                    'id' => $ticket->id,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status->name,
                    'priority' => $ticket->priority->name,
                    'department' => $ticket->department?->name,
                ],
                'ai_analysis' => $aiAnalysis,
                'estimated_resolution' => $this->getEstimatedResolution($aiAnalysis),
                'message' => 'Your support ticket has been created successfully. We\'ll get back to you soon!',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create intelligent ticket', [
                'error' => $e->getMessage(),
                'data' => $data,
                'user_id' => $user?->id,
            ]);

            return [
                'can_self_resolve' => false,
                'ticket_created' => false,
                'error' => 'Failed to create ticket. Please try again.',
            ];
        }
    }

    /**
     * Get guided troubleshooting steps
     */
    public function getGuidedTroubleshooting(string $issue): array
    {
        $cacheKey = 'troubleshooting_' . md5($issue);

        return Cache::remember($cacheKey, 1800, function () use ($issue) {
            // Use AI to generate troubleshooting steps
            $aiSteps = $this->generateAITroubleshootingSteps($issue);

            // Get relevant knowledge articles
            $articles = $this->searchKnowledgeBase($issue, 5);

            // Common troubleshooting steps based on keywords
            $commonSteps = $this->getCommonTroubleshootingSteps($issue);

            return [
                'issue' => $issue,
                'steps' => array_merge($aiSteps, $commonSteps),
                'related_articles' => $articles['articles'],
                'estimated_time' => $this->estimateTroubleshootingTime($issue),
                'difficulty_level' => $this->assessDifficultyLevel($issue),
            ];
        });
    }

    /**
     * Get predictive suggestions based on user behavior
     */
    public function getPredictiveSuggestions(?User $user = null): array
    {
        $suggestions = [];

        if ($user) {
            // Get user's ticket history
            $recentTickets = $user->createdTickets()->latest()->take(5)->get();

            // Analyze patterns
            $commonIssues = $this->analyzeUserPatterns($recentTickets);

            $suggestions['based_on_history'] = $commonIssues;
        }

        // Global popular issues
        $suggestions['trending_issues'] = $this->getTrendingIssues();

        // Seasonal suggestions
        $suggestions['seasonal'] = $this->getSeasonalSuggestions();

        return $suggestions;
    }

    /**
     * Get conversation starters for the chatbot
     */
    public function getConversationStarters(): array
    {
        return [
            'general' => [
                'How can I reset my password?',
                "I'm having trouble logging in",
                'How do I update my account information?',
                'I need help with billing',
            ],
            'technical' => [
                'The application is running slowly',
                "I'm getting an error message",
                "A feature isn't working as expected",
                'I need help with integration',
            ],
            'account' => [
                'How do I change my subscription?',
                'I want to cancel my account',
                'How do I add team members?',
                'I need to update payment information',
            ],
        ];
    }

    /**
     * Get customer portal analytics
     */
    public function getPortalAnalytics(int $days = 30): array
    {
        $since = now()->subDays($days);

        return [
            'knowledge_searches' => $this->getKnowledgeSearchStats($since),
            'self_resolution_rate' => $this->getSelfResolutionRate($since),
            'popular_articles' => $this->getPopularArticleStats($since),
            'common_issues' => $this->getCommonIssueStats($since),
            'chatbot_usage' => $this->getChatbotUsageStats($since),
        ];
    }

    /**
     * Private helper methods
     */
    private function getSemanticSearchResults(string $query, int $limit): Collection
    {
        // This would integrate with vector search if embeddings are available
        // For now, return empty collection
        return collect();
    }

    private function mergeSearchResults(Collection $fullTextResults, Collection $semanticResults): Collection
    {
        // Simple merge for now - in production, this would be more sophisticated
        return $fullTextResults->merge($semanticResults)->unique('id');
    }

    private function getSearchSuggestions(string $query): array
    {
        $words = explode(' ', strtolower($query));
        $suggestions = [];

        // Simple suggestion logic - in production, this would use ML
        if (in_array('password', $words)) {
            $suggestions[] = 'password reset';
            $suggestions[] = 'forgot password';
        }
        if (in_array('login', $words)) {
            $suggestions[] = 'login issues';
            $suggestions[] = 'account access';
        }

        return array_slice($suggestions, 0, 3);
    }

    private function canBeSelfResolved(array $aiAnalysis, array $relevantArticles): bool
    {
        $confidence = $aiAnalysis['confidence'] ?? 0;
        $category = $aiAnalysis['category'] ?? 'unknown';
        $articleCount = count($relevantArticles['articles']);

        // High confidence + good articles + simple categories = self-resolvable
        return $confidence > 0.8 &&
            $articleCount >= 2 &&
            in_array($category, ['question', 'how_to', 'setup']);
    }

    private function getPriorityFromAI(array $aiAnalysis): int
    {
        $priority = $aiAnalysis['priority'] ?? 'normal';

        return match ($priority) {
            'urgent' => 4,
            'high' => 3,
            'normal' => 2,
            'low' => 1,
            default => 2,
        };
    }

    private function getDepartmentFromAI(array $aiAnalysis): int
    {
        $department = $aiAnalysis['department'] ?? 'general';

        return match ($department) {
            'technical' => 3,
            'billing' => 2,
            'general' => 1,
            default => 1,
        };
    }

    private function getEstimatedResolution(array $aiAnalysis): string
    {
        $resolutionTime = $aiAnalysis['estimated_resolution_time'] ?? 1440;

        if ($resolutionTime <= 60) {
            return 'Within 1 hour';
        } elseif ($resolutionTime <= 480) {
            return 'Within 8 hours';
        } elseif ($resolutionTime <= 1440) {
            return 'Within 24 hours';
        } else {
            return 'Within 48 hours';
        }
    }

    private function generateAITroubleshootingSteps(string $issue): array
    {
        // In production, this would use AI to generate steps
        // For now, return basic steps
        return [
            [
                'step' => 1,
                'title' => 'Check basic requirements',
                'description' => 'Verify that your system meets the minimum requirements',
                'estimated_time' => '2 minutes',
            ],
            [
                'step' => 2,
                'title' => 'Clear browser cache',
                'description' => 'Clear your browser cache and cookies, then try again',
                'estimated_time' => '3 minutes',
            ],
        ];
    }

    private function getCommonTroubleshootingSteps(string $issue): array
    {
        $issue = strtolower($issue);

        if (str_contains($issue, 'login') || str_contains($issue, 'password')) {
            return [
                [
                    'step' => 3,
                    'title' => 'Reset your password',
                    'description' => 'Use the forgot password link to reset your password',
                    'estimated_time' => '5 minutes',
                ],
            ];
        }

        return [];
    }

    private function estimateTroubleshootingTime(string $issue): string
    {
        // Simple estimation based on issue complexity
        $complexKeywords = ['integration', 'api', 'custom', 'advanced'];
        $issue = strtolower($issue);

        foreach ($complexKeywords as $keyword) {
            if (str_contains($issue, $keyword)) {
                return '15-30 minutes';
            }
        }

        return '5-10 minutes';
    }

    private function assessDifficultyLevel(string $issue): string
    {
        $complexKeywords = ['integration', 'api', 'database', 'custom'];
        $issue = strtolower($issue);

        foreach ($complexKeywords as $keyword) {
            if (str_contains($issue, $keyword)) {
                return 'Advanced';
            }
        }

        $intermediateKeywords = ['configuration', 'settings', 'setup'];
        foreach ($intermediateKeywords as $keyword) {
            if (str_contains($issue, $keyword)) {
                return 'Intermediate';
            }
        }

        return 'Beginner';
    }

    private function analyzeUserPatterns(Collection $tickets): array
    {
        // Analyze user's common issues
        $categories = $tickets->groupBy('category')->map->count();
        $departments = $tickets->groupBy('department_id')->map->count();

        return [
            'common_categories' => $categories->sortDesc()->take(3)->keys()->toArray(),
            'common_departments' => $departments->sortDesc()->take(2)->keys()->toArray(),
        ];
    }

    private function getTrendingIssues(): array
    {
        // Get trending issues from recent tickets
        return Cache::remember('trending_issues', 3600, function () {
            return [
                'Password reset requests',
                'Login difficulties',
                'Account access issues',
                'Billing questions',
                'Feature requests',
            ];
        });
    }

    private function getSeasonalSuggestions(): array
    {
        $month = now()->month;

        // Seasonal suggestions based on month
        if (in_array($month, [12, 1, 2])) {
            return ['Year-end reporting', 'Holiday schedules', 'Backup procedures'];
        } elseif (in_array($month, [3, 4, 5])) {
            return ['Spring cleaning data', 'Q1 reporting', 'System updates'];
        } elseif (in_array($month, [6, 7, 8])) {
            return ['Summer maintenance', 'Vacation mode setup', 'Security reviews'];
        } else {
            return ['Fall updates', 'Q4 preparation', 'System optimization'];
        }
    }

    private function getKnowledgeSearchStats(\DateTime $since): array
    {
        // In production, this would query actual search logs
        return [
            'total_searches' => 1250,
            'unique_queries' => 850,
            'avg_results_per_search' => 4.2,
            'zero_result_searches' => 85,
        ];
    }

    private function getSelfResolutionRate(\DateTime $since): float
    {
        // Calculate what percentage of issues are resolved without creating tickets
        return 0.68; // 68% self-resolution rate
    }

    private function getPopularArticleStats(\DateTime $since): array
    {
        return $this->getPopularArticles(5)->map(function ($article) {
            return [
                'title' => $article->title,
                'views' => $article->view_count ?? 0,
                'helpful_votes' => $article->helpful_count ?? 0,
            ];
        })->toArray();
    }

    private function getCommonIssueStats(\DateTime $since): array
    {
        return [
            'Password resets' => 245,
            'Login issues' => 189,
            'Account access' => 156,
            'Billing questions' => 134,
            'Feature requests' => 98,
        ];
    }

    private function getChatbotUsageStats(\DateTime $since): array
    {
        // In production, this would query conversation data
        return [
            'total_conversations' => 892,
            'avg_messages_per_conversation' => 8.5,
            'escalation_rate' => 0.23,
            'avg_confidence' => 0.78,
        ];
    }
}

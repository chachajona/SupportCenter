<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CustomerPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CustomerPortalController extends Controller
{
    public function __construct(
        private readonly CustomerPortalService $portalService
    ) {}

    /**
     * Get predictive suggestions for the user
     */
    public function getSuggestions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $suggestions = $this->portalService->getPredictiveSuggestions($user);

            return response()->json([
                'success' => true,
                'suggestions' => $suggestions,
                'conversation_starters' => $this->portalService->getConversationStarters(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get portal suggestions', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load suggestions.',
            ], 500);
        }
    }

    /**
     * Get guided troubleshooting steps
     */
    public function getTroubleshooting(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'issue' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $issue = $request->get('issue');
            $troubleshooting = $this->portalService->getGuidedTroubleshooting($issue);

            return response()->json([
                'success' => true,
                'troubleshooting' => $troubleshooting,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get troubleshooting steps', [
                'error' => $e->getMessage(),
                'issue' => $request->get('issue'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate troubleshooting steps.',
            ], 500);
        }
    }

    /**
     * Create an intelligent ticket with AI assistance
     */
    public function createIntelligentTicket(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'category' => 'sometimes|string|max:100',
            'priority' => 'sometimes|string|in:low,normal,high,urgent',
            'self_service_attempted' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $data = $request->all();

            $result = $this->portalService->createIntelligentTicket($data, $user);

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create intelligent ticket', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket. Please try again.',
            ], 500);
        }
    }

    /**
     * Search knowledge base with intelligent features
     */
    public function searchKnowledgeBase(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|max:500',
            'limit' => 'sometimes|integer|min:1|max:50',
            'category' => 'sometimes|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = $request->get('query');
            $limit = $request->get('limit', 10);

            $searchResults = $this->portalService->searchKnowledgeBase($query, $limit);

            return response()->json([
                'success' => true,
                'search_results' => $searchResults,
                'query' => $query,
            ]);

        } catch (\Exception $e) {
            Log::error('Knowledge base search failed', [
                'error' => $e->getMessage(),
                'query' => $request->get('query'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Search failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Get popular knowledge articles
     */
    public function getPopularArticles(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 10);
            $articles = $this->portalService->getPopularArticles($limit);

            return response()->json([
                'success' => true,
                'articles' => $articles->map(function ($article) {
                    return [
                        'id' => $article->id,
                        'title' => $article->title,
                        'slug' => $article->slug,
                        'excerpt' => substr(strip_tags($article->content), 0, 200).'...',
                        'category' => $article->category?->name,
                        'helpful_count' => $article->helpful_count ?? 0,
                        'view_count' => $article->view_count ?? 0,
                        'url' => route('knowledge.show', $article->slug),
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get popular articles', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load popular articles.',
            ], 500);
        }
    }

    /**
     * Get recent knowledge articles
     */
    public function getRecentArticles(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 10);
            $articles = $this->portalService->getRecentArticles($limit);

            return response()->json([
                'success' => true,
                'articles' => $articles->map(function ($article) {
                    return [
                        'id' => $article->id,
                        'title' => $article->title,
                        'slug' => $article->slug,
                        'excerpt' => substr(strip_tags($article->content), 0, 200).'...',
                        'category' => $article->category?->name,
                        'helpful_count' => $article->helpful_count ?? 0,
                        'view_count' => $article->view_count ?? 0,
                        'url' => route('knowledge.show', $article->slug),
                        'created_at' => $article->created_at->toISOString(),
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get recent articles', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load recent articles.',
            ], 500);
        }
    }

    /**
     * Get customer portal analytics (admin only)
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        // Check if user has admin permissions
        if (! $request->user() || ! $request->user()->hasPermissionTo('analytics.view_all')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions.',
            ], 403);
        }

        try {
            $days = $request->get('days', 30);
            $analytics = $this->portalService->getPortalAnalytics($days);

            return response()->json([
                'success' => true,
                'analytics' => $analytics,
                'date_range' => "{$days} days",
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get customer portal analytics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate analytics.',
            ], 500);
        }
    }

    /**
     * Submit feedback for an article
     */
    public function submitArticleFeedback(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'article_id' => 'required|integer|exists:knowledge_articles,id',
            'helpful' => 'required|boolean',
            'feedback' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $articleId = $request->get('article_id');
            $helpful = $request->get('helpful');
            $feedback = $request->get('feedback');
            $user = $request->user();

            // In production, this would save to a feedback table
            // For now, just update the article's helpful count
            $article = \App\Models\KnowledgeArticle::findOrFail($articleId);

            if ($helpful) {
                $article->increment('helpful_count');
            }

            // Log the feedback for analysis
            Log::info('Article feedback submitted', [
                'article_id' => $articleId,
                'helpful' => $helpful,
                'feedback' => $feedback,
                'user_id' => $user?->id,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your feedback!',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to submit article feedback', [
                'error' => $e->getMessage(),
                'article_id' => $request->get('article_id'),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback.',
            ], 500);
        }
    }

    /**
     * Get smart ticket creation assistance
     */
    public function getTicketCreationAssistance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $subject = $request->get('subject', '');
            $description = $request->get('description', '');

            // Analyze the input and provide suggestions
            $assistance = [
                'related_articles' => [],
                'suggested_category' => 'general',
                'suggested_priority' => 'normal',
                'suggested_department' => 'support',
                'pre_submission_checklist' => [
                    'Check the knowledge base for similar issues',
                    'Ensure you\'ve provided all relevant details',
                    'Include any error messages or screenshots',
                    'Specify your operating system and browser',
                ],
                'field_suggestions' => [],
            ];

            // If there's content, analyze it
            if ($subject || $description) {
                $searchQuery = trim($subject.' '.$description);
                $relatedArticles = $this->portalService->searchKnowledgeBase($searchQuery, 3);
                $assistance['related_articles'] = $relatedArticles['articles'];

                // Simple keyword-based categorization
                $content = strtolower($searchQuery);
                if (str_contains($content, 'login') || str_contains($content, 'password')) {
                    $assistance['suggested_category'] = 'account';
                    $assistance['suggested_department'] = 'technical';
                } elseif (str_contains($content, 'billing') || str_contains($content, 'payment')) {
                    $assistance['suggested_category'] = 'billing';
                    $assistance['suggested_department'] = 'billing';
                } elseif (str_contains($content, 'bug') || str_contains($content, 'error')) {
                    $assistance['suggested_category'] = 'technical';
                    $assistance['suggested_priority'] = 'high';
                    $assistance['suggested_department'] = 'technical';
                }
            }

            return response()->json([
                'success' => true,
                'assistance' => $assistance,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get ticket creation assistance', [
                'error' => $e->getMessage(),
                'subject' => $request->get('subject'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate assistance.',
            ], 500);
        }
    }
}

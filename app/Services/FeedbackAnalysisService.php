<?php

namespace App\Services;

use App\Models\UserFeedback;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FeedbackAnalysisService
{
    /**
     * Get the most requested features from user feedback
     */
    public function getTopRequestedFeatures(int $limit = 10): Collection
    {
        return Cache::remember('top_requested_features', 300, function () use ($limit) {
            return UserFeedback::where('category', 'feature_request')
                ->where('status', 'open')
                ->selectRaw('description, subject, COUNT(*) as request_count')
                ->groupBy('description', 'subject')
                ->orderByDesc('request_count')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                    return [
                        'description' => $item->description,
                        'subject' => $item->subject,
                        'request_count' => $item->request_count,
                        'priority_score' => $this->calculatePriorityScore($item),
                    ];
                });
        });
    }

    /**
     * Get urgent bug reports that need immediate attention
     */
    public function getUrgentBugReports(): Collection
    {
        return UserFeedback::where('category', 'bug_report')
            ->whereIn('priority', ['high', 'critical'])
            ->where('status', 'open')
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($feedback) {
                return [
                    'id' => $feedback->id,
                    'subject' => $feedback->subject,
                    'description' => $feedback->description,
                    'priority' => $feedback->priority,
                    'feature_area' => $feedback->feature_area,
                    'user' => $feedback->user->name,
                    'created_at' => $feedback->created_at,
                    'age_days' => $feedback->age_in_days,
                    'is_critical' => $feedback->priority === 'critical',
                ];
            });
    }

    /**
     * Get user satisfaction metrics and trends
     */
    public function getUserSatisfactionMetrics(): array
    {
        $metrics = Cache::remember('user_satisfaction_metrics', 300, function () {
            $totalFeedback = UserFeedback::where('category', 'general_feedback')->count();

            if ($totalFeedback === 0) {
                return [
                    'total_feedback' => 0,
                    'satisfaction_rate' => 0,
                    'trending_issues' => collect(),
                    'satisfaction_trend' => [],
                ];
            }

            $positiveFeedback = UserFeedback::where('category', 'general_feedback')
                ->whereIn('priority', ['low', 'medium'])
                ->count();

            $satisfactionRate = round(($positiveFeedback / $totalFeedback) * 100, 1);

            return [
                'total_feedback' => $totalFeedback,
                'satisfaction_rate' => $satisfactionRate,
                'trending_issues' => $this->getTrendingIssues(),
                'satisfaction_trend' => $this->getSatisfactionTrend(),
            ];
        });

        return $metrics;
    }

    /**
     * Get trending issues from the last week
     */
    public function getTrendingIssues(): Collection
    {
        return UserFeedback::where('created_at', '>=', now()->subDays(7))
            ->where('category', 'bug_report')
            ->selectRaw('feature_area, COUNT(*) as issue_count, AVG(CASE WHEN priority = "critical" THEN 3 WHEN priority = "high" THEN 2 WHEN priority = "medium" THEN 1 ELSE 0 END) as avg_severity')
            ->groupBy('feature_area')
            ->orderByDesc('issue_count')
            ->get()
            ->map(function ($item) {
                return [
                    'feature_area' => $item->feature_area,
                    'issue_count' => $item->issue_count,
                    'avg_severity' => round($item->avg_severity, 1),
                    'area_name' => UserFeedback::FEATURE_AREAS[$item->feature_area] ?? $item->feature_area,
                ];
            });
    }

    /**
     * Get satisfaction trend over the last 30 days
     */
    public function getSatisfactionTrend(): array
    {
        $trend = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');

            $dayFeedback = UserFeedback::where('category', 'general_feedback')
                ->whereDate('created_at', $date)
                ->get();

            if ($dayFeedback->count() > 0) {
                $positive = $dayFeedback->whereIn('priority', ['low', 'medium'])->count();
                $satisfactionRate = round(($positive / $dayFeedback->count()) * 100, 1);
            } else {
                $satisfactionRate = null; // No feedback for this day
            }

            $trend[] = [
                'date' => $date,
                'satisfaction_rate' => $satisfactionRate,
                'total_feedback' => $dayFeedback->count(),
            ];
        }

        return $trend;
    }

    /**
     * Analyze feedback patterns and provide insights
     */
    public function getFeedbackInsights(): array
    {
        return Cache::remember('feedback_insights', 600, function () {
            $insights = [];

            // Most problematic feature areas
            $problematicAreas = UserFeedback::where('category', 'bug_report')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('feature_area, COUNT(*) as bug_count')
                ->groupBy('feature_area')
                ->orderByDesc('bug_count')
                ->limit(3)
                ->get();

            if ($problematicAreas->count() > 0) {
                $insights[] = [
                    'type' => 'high_bug_areas',
                    'title' => 'Areas with Most Bug Reports',
                    'data' => $problematicAreas->map(fn ($area) => [
                        'area' => UserFeedback::FEATURE_AREAS[$area->feature_area] ?? $area->feature_area,
                        'count' => $area->bug_count,
                    ]),
                    'priority' => 'high',
                ];
            }

            // Feature requests gaining momentum
            $trendingRequests = UserFeedback::where('category', 'feature_request')
                ->where('created_at', '>=', now()->subDays(14))
                ->selectRaw('subject, COUNT(*) as request_count')
                ->groupBy('subject')
                ->having('request_count', '>=', 3)
                ->orderByDesc('request_count')
                ->limit(5)
                ->get();

            if ($trendingRequests->count() > 0) {
                $insights[] = [
                    'type' => 'trending_requests',
                    'title' => 'Feature Requests Gaining Momentum',
                    'data' => $trendingRequests,
                    'priority' => 'medium',
                ];
            }

            // Users providing most feedback
            $activeUsers = UserFeedback::with('user')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('user_id, COUNT(*) as feedback_count')
                ->groupBy('user_id')
                ->orderByDesc('feedback_count')
                ->limit(5)
                ->get();

            if ($activeUsers->count() > 0) {
                $insights[] = [
                    'type' => 'active_users',
                    'title' => 'Most Active Feedback Contributors',
                    'data' => $activeUsers->map(fn ($user) => [
                        'user' => $user->user->name,
                        'feedback_count' => $user->feedback_count,
                    ]),
                    'priority' => 'low',
                ];
            }

            return $insights;
        });
    }

    /**
     * Get implementation candidates - feedback ready for development
     */
    public function getImplementationCandidates(): Collection
    {
        return UserFeedback::where('category', 'feature_request')
            ->where('status', 'under_review')
            ->selectRaw('subject, description, COUNT(*) as demand, AVG(CASE WHEN priority = "high" THEN 3 WHEN priority = "medium" THEN 2 ELSE 1 END) as avg_priority')
            ->groupBy('subject', 'description')
            ->having('demand', '>=', 2) // At least 2 users requested it
            ->orderByDesc('demand')
            ->orderByDesc('avg_priority')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'subject' => $item->subject,
                    'description' => $item->description,
                    'demand' => $item->demand,
                    'priority_score' => round($item->avg_priority, 1),
                    'implementation_score' => $this->calculateImplementationScore($item),
                ];
            });
    }

    /**
     * Calculate priority score for feature requests
     */
    private function calculatePriorityScore($item): float
    {
        $baseScore = $item->request_count * 10;

        // Add bonus for recent feedback
        $recentRequests = UserFeedback::where('subject', $item->subject)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $recencyBonus = $recentRequests * 5;

        return $baseScore + $recencyBonus;
    }

    /**
     * Calculate implementation score based on demand and complexity estimate
     */
    private function calculateImplementationScore($item): float
    {
        $demandScore = $item->demand * 20;
        $priorityScore = $item->avg_priority * 10;

        // Simple complexity estimation based on description length and keywords
        $complexityPenalty = $this->estimateComplexity($item->description) * -5;

        return max(0, $demandScore + $priorityScore + $complexityPenalty);
    }

    /**
     * Estimate complexity based on description content
     */
    private function estimateComplexity(string $description): int
    {
        $complexKeywords = [
            'integration',
            'api',
            'database',
            'migration',
            'authentication',
            'security',
            'performance',
            'scalability',
            'architecture',
        ];

        $complexity = 1; // Base complexity

        foreach ($complexKeywords as $keyword) {
            if (stripos($description, $keyword) !== false) {
                $complexity++;
            }
        }

        // Length-based complexity
        if (strlen($description) > 500) {
            $complexity++;
        }

        return min($complexity, 5); // Cap at 5
    }

    /**
     * Generate feedback summary report
     */
    public function generateSummaryReport(): array
    {
        $report = Cache::remember('feedback_summary_report', 600, function () {
            $totalFeedback = UserFeedback::count();
            $openIssues = UserFeedback::where('status', 'open')->count();
            $urgentIssues = UserFeedback::whereIn('priority', ['high', 'critical'])
                ->where('status', 'open')
                ->count();

            return [
                'overview' => [
                    'total_feedback' => $totalFeedback,
                    'open_issues' => $openIssues,
                    'urgent_issues' => $urgentIssues,
                    'resolution_rate' => $totalFeedback > 0 ? round((($totalFeedback - $openIssues) / $totalFeedback) * 100, 1) : 0,
                ],
                'categories' => $this->getCategoryBreakdown(),
                'trends' => $this->getFeedbackTrends(),
                'top_requests' => $this->getTopRequestedFeatures(5),
                'urgent_bugs' => $this->getUrgentBugReports()->take(5),
                'satisfaction' => $this->getUserSatisfactionMetrics(),
                'insights' => $this->getFeedbackInsights(),
            ];
        });

        return $report;
    }

    /**
     * Get breakdown by category
     */
    private function getCategoryBreakdown(): array
    {
        return UserFeedback::selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->get()
            ->mapWithKeys(function ($item) {
                return [
                    UserFeedback::CATEGORIES[$item->category] ?? $item->category => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get feedback trends over time
     */
    private function getFeedbackTrends(): array
    {
        $trends = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subWeeks($i);
            $weekStart = $date->startOfWeek();
            $weekEnd = $date->endOfWeek();

            $weeklyFeedback = UserFeedback::whereBetween('created_at', [$weekStart, $weekEnd])
                ->selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->get()
                ->pluck('count', 'category')
                ->toArray();

            $trends[] = [
                'week' => $date->format('M j'),
                'total' => array_sum($weeklyFeedback),
                'by_category' => $weeklyFeedback,
            ];
        }

        return $trends;
    }
}

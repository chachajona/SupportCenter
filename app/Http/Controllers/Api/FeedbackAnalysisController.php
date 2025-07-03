<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FeedbackAnalysisService;
use Illuminate\Http\JsonResponse;

final class FeedbackAnalysisController extends Controller
{
    public function __construct(private readonly FeedbackAnalysisService $analysisService)
    {
    }

    /**
     * Return a summary of user feedback statistics.
     */
    public function summary(): JsonResponse
    {
        $metrics = $this->analysisService->getUserSatisfactionMetrics();

        return response()->json($metrics);
    }

    /**
     * Return insights, such as urgent bugs and trending issues.
     */
    public function insights(): JsonResponse
    {
        return response()->json([
            'urgent_bugs' => $this->analysisService->getUrgentBugReports(),
            'trending_issues' => $this->analysisService->getUserSatisfactionMetrics()['trending_issues'] ?? [],
        ]);
    }

    /**
     * Return top requested features.
     */
    public function topRequests(): JsonResponse
    {
        return response()->json($this->analysisService->getTopRequestedFeatures());
    }
}

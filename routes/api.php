<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Customer Portal & Chatbot routes (public for customer self-service)
Route::prefix('portal')->group(function () {
    Route::post('/chat/start', [App\Http\Controllers\Api\ChatbotController::class, 'startConversation']);
    Route::post('/chat/message', [App\Http\Controllers\Api\ChatbotController::class, 'sendMessage']);
    Route::get('/chat/{sessionId}/history', [App\Http\Controllers\Api\ChatbotController::class, 'getConversationHistory']);
    Route::post('/chat/escalate', [App\Http\Controllers\Api\ChatbotController::class, 'escalateToHuman']);
    Route::post('/chat/end', [App\Http\Controllers\Api\ChatbotController::class, 'endConversation']);

    // Customer Portal features
    Route::get('/suggestions', [App\Http\Controllers\Api\CustomerPortalController::class, 'getSuggestions']);
    Route::get('/troubleshooting', [App\Http\Controllers\Api\CustomerPortalController::class, 'getTroubleshooting']);
    Route::post('/tickets/intelligent', [App\Http\Controllers\Api\CustomerPortalController::class, 'createIntelligentTicket']);
    Route::get('/knowledge/search', [App\Http\Controllers\Api\CustomerPortalController::class, 'searchKnowledgeBase']);
    Route::get('/knowledge/popular', [App\Http\Controllers\Api\CustomerPortalController::class, 'getPopularArticles']);
    Route::get('/knowledge/recent', [App\Http\Controllers\Api\CustomerPortalController::class, 'getRecentArticles']);
    Route::post('/knowledge/feedback', [App\Http\Controllers\Api\CustomerPortalController::class, 'submitArticleFeedback']);
    Route::get('/tickets/assistance', [App\Http\Controllers\Api\CustomerPortalController::class, 'getTicketCreationAssistance']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user/permissions', function (Request $request) {
        $user = $request->user();

        // Load roles, permissions and department relations in one go
        $user->load(['roles.permissions', 'department']);

        // Flatten permissions to unique list of names
        $permissions = $user->getAllPermissions()->pluck('name')->unique()->values();

        return response()->json([
            'roles' => $user->roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'display_name' => $r->display_name,
                'description' => $r->description,
                'hierarchy_level' => $r->hierarchy_level,
                'is_active' => $r->is_active,
            ]),
            'permissions' => $permissions,
            'departments' => $user->department ? [
                [
                    'id' => $user->department->id,
                    'name' => $user->department->name,
                    'parent_id' => $user->department->parent_id,
                    'path' => $user->department->path,
                    'is_active' => $user->department->is_active,
                ],
            ] : [],
        ]);
    });

    // Ticket API routes
    Route::apiResource('tickets', App\Http\Controllers\Api\TicketController::class);
    Route::post('tickets/{ticket}/assign', [App\Http\Controllers\Api\TicketController::class, 'assign'])
        ->name('api.tickets.assign');
    Route::post('tickets/{ticket}/responses', [App\Http\Controllers\Api\TicketController::class, 'addResponse'])
        ->name('api.tickets.responses.store');

    // Knowledge Base API routes
    Route::get('knowledge/articles', [App\Http\Controllers\Api\KnowledgeArticleController::class, 'index'])
        ->name('api.knowledge.articles.index');
    Route::get('knowledge/articles/{article}', [App\Http\Controllers\Api\KnowledgeArticleController::class, 'show'])
        ->name('api.knowledge.articles.show');
    Route::get('knowledge/search', [App\Http\Controllers\Api\KnowledgeArticleController::class, 'search'])
        ->name('api.knowledge.search');
    Route::get('knowledge/popular', [App\Http\Controllers\Api\KnowledgeArticleController::class, 'popular'])
        ->name('api.knowledge.popular');
    Route::get('knowledge/recent', [App\Http\Controllers\Api\KnowledgeArticleController::class, 'recent'])
        ->name('api.knowledge.recent');

    // Analytics API routes
    Route::middleware('permission:helpdesk_analytics.view')->group(function () {
        Route::get('analytics/dashboard', [App\Http\Controllers\Admin\HelpdeskAnalyticsController::class, 'index'])
            ->name('api.analytics.dashboard');
        Route::get('analytics/metrics', [App\Http\Controllers\Admin\HelpdeskAnalyticsController::class, 'getMetrics'])
            ->name('api.analytics.metrics');
    });

    // Phase 4: Export functionality
    Route::prefix('tickets')->group(function () {
        Route::get('export/csv', [App\Http\Controllers\TicketExportController::class, 'exportCsv'])->name('tickets.export.csv');
        Route::get('export/summary', [App\Http\Controllers\TicketExportController::class, 'exportSummary'])->name('tickets.export.summary');
        Route::get('export/stats', [App\Http\Controllers\TicketExportController::class, 'getExportStats'])->name('tickets.export.stats');
    });

    // User feedback endpoints
    Route::prefix('feedback')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\UserFeedbackController::class, 'index'])->name('feedback.index');
        Route::post('/', [App\Http\Controllers\Api\UserFeedbackController::class, 'store'])->name('feedback.store');
        Route::get('{feedback}', [App\Http\Controllers\Api\UserFeedbackController::class, 'show'])->name('feedback.show');
        Route::put('{feedback}', [App\Http\Controllers\Api\UserFeedbackController::class, 'update'])->name('feedback.update');
        Route::delete('{feedback}', [App\Http\Controllers\Api\UserFeedbackController::class, 'destroy'])->name('feedback.destroy');

        // Admin feedback management
        Route::middleware('can:manage-feedback')->group(function () {
            Route::get('analysis/summary', [App\Http\Controllers\Api\FeedbackAnalysisController::class, 'summary'])->name('feedback.analysis.summary');
            Route::get('analysis/insights', [App\Http\Controllers\Api\FeedbackAnalysisController::class, 'insights'])->name('feedback.analysis.insights');
            Route::get('analysis/top-requests', [App\Http\Controllers\Api\FeedbackAnalysisController::class, 'topRequests'])->name('feedback.analysis.top-requests');
            Route::post('{feedback}/mark-reviewed', [App\Http\Controllers\Api\UserFeedbackController::class, 'markReviewed'])->name('feedback.mark-reviewed');
            Route::post('{feedback}/mark-implemented', [App\Http\Controllers\Api\UserFeedbackController::class, 'markImplemented'])->name('feedback.mark-implemented');
        });
    });

    // Health and monitoring endpoints
    Route::prefix('system')->middleware('can:view-system-health')->group(function () {
        Route::get('health', [App\Http\Controllers\Api\SystemHealthController::class, 'health'])->name('system.health');
        Route::get('performance', [App\Http\Controllers\Api\SystemHealthController::class, 'performance'])->name('system.performance');
        Route::get('metrics', [App\Http\Controllers\Api\SystemHealthController::class, 'metrics'])->name('system.metrics');
    });

    // Workflow Management API routes (Phase 5B)
    Route::prefix('workflows')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\WorkflowController::class, 'index'])->name('workflows.index');
        Route::post('/', [App\Http\Controllers\Api\WorkflowController::class, 'store'])->name('workflows.store');

        // Workflow templates (must be before parameterized routes)
        Route::get('templates', [App\Http\Controllers\Api\WorkflowController::class, 'templates'])->name('workflows.templates');
        Route::post('templates/{template}/create', [App\Http\Controllers\Api\WorkflowController::class, 'createFromTemplate'])->name('workflows.create-from-template');

        Route::get('{workflow}', [App\Http\Controllers\Api\WorkflowController::class, 'show'])->name('workflows.show');
        Route::put('{workflow}', [App\Http\Controllers\Api\WorkflowController::class, 'update'])->name('workflows.update');
        Route::delete('{workflow}', [App\Http\Controllers\Api\WorkflowController::class, 'destroy'])->name('workflows.destroy');

        // Workflow execution
        Route::post('{workflow}/execute', [App\Http\Controllers\Api\WorkflowController::class, 'execute'])->name('workflows.execute');
        Route::post('{workflow}/toggle', [App\Http\Controllers\Api\WorkflowController::class, 'toggle'])->name('workflows.toggle');
        Route::post('{workflow}/test', [App\Http\Controllers\Api\WorkflowController::class, 'test'])->name('workflows.test');

        // Workflow executions
        Route::get('{workflow}/executions', [App\Http\Controllers\Api\WorkflowController::class, 'executions'])->name('workflows.executions');
    });

    // Chatbot Analytics (Phase 5C)
    Route::get('chatbot/analytics', [App\Http\Controllers\Api\ChatbotController::class, 'getAnalytics'])
        ->middleware('permission:analytics.view_all')
        ->name('chatbot.analytics');

    // Customer Portal Analytics (Phase 5C)
    Route::get('portal/analytics', [App\Http\Controllers\Api\CustomerPortalController::class, 'getAnalytics'])
        ->middleware('permission:analytics.view_all')
        ->name('portal.analytics');
});

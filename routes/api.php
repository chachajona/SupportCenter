<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user/permissions', function (Request $request) {
        $user = $request->user();

        // Load roles, permissions and department relations in one go
        $user->load(['roles.permissions', 'department']);

        // Flatten permissions to unique list of names
        $permissions = $user->getAllPermissions()->pluck('name')->unique()->values();

        return response()->json([
            'roles' => $user->roles->map(fn($r) => [
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
                ]
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
});

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KnowledgeArticleResource;
use App\Models\KnowledgeArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

/**
 * API Controller for knowledge article management operations.
 */
final class KnowledgeArticleController extends Controller
{
    /**
     * Display a listing of knowledge articles.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = KnowledgeArticle::with([
            'category:id,name',
            'department:id,name',
            'author:id,name,email',
        ])
            ->published()
            ->select([
                'id',
                'title',
                'summary',
                'category_id',
                'department_id',
                'author_id',
                'view_count',
                'tags',
                'published_at',
                'created_at',
                'updated_at',
            ]);

        // Apply department scoping for non-public articles
        $user = Auth::user();
        if ($user) {
            $userDepartmentId = $user->getAttribute('department_id');
            $query->forDepartment($userDepartmentId);
        } else {
            $query->public();
        }

        // Apply search filter
        if ($search = $request->get('search')) {
            $query->search($search);
        }

        // Apply category filter
        if ($categoryId = $request->get('category_id')) {
            $query->inCategory((int) $categoryId);
        }

        // Apply tag filter
        if ($tag = $request->get('tag')) {
            $query->withTag($tag);
        }

        // Apply sorting
        $sort = $request->get('sort', 'recent');
        switch ($sort) {
            case 'popular':
                $query->popular();
                break;
            case 'recent':
            default:
                $query->recent();
                break;
        }

        $articles = $query->paginate($request->get('per_page', 20));

        return KnowledgeArticleResource::collection($articles);
    }

    /**
     * Display the specified knowledge article.
     */
    public function show(KnowledgeArticle $article): KnowledgeArticleResource
    {
        // Check if user can view this article
        $user = Auth::user();
        if (! $article->is_public && (! $user || ! $user->hasDepartmentAccess($article->department_id))) {
            abort(403, 'Unauthorized to view this article');
        }

        // Increment view count
        $article->incrementViewCount();

        $article->load([
            'category:id,name,description',
            'department:id,name',
            'author:id,name,email',
        ]);

        return new KnowledgeArticleResource($article);
    }

    /**
     * Search knowledge articles.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:3|max:255',
            'category_id' => 'nullable|exists:knowledge_categories,id',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $query = KnowledgeArticle::published()
            ->search($validated['q'])
            ->with(['category:id,name', 'department:id,name']);

        // Apply department scoping
        $user = Auth::user();
        if ($user) {
            $userDepartmentId = $user->getAttribute('department_id');
            $query->forDepartment($userDepartmentId);
        } else {
            $query->public();
        }

        // Apply additional filters
        if (isset($validated['category_id'])) {
            $query->inCategory($validated['category_id']);
        }

        if (isset($validated['department_id'])) {
            $query->where('department_id', $validated['department_id']);
        }

        $results = $query->limit(10)->get()->map(function ($article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'summary' => $article->summary,
                'category' => $article->category?->name,
                'department' => $article->department?->name,
                'view_count' => $article->view_count,
                'published_at' => $article->published_at->toISOString(),
            ];
        });

        return response()->json([
            'query' => $validated['q'],
            'results' => $results,
            'total' => $results->count(),
        ]);
    }

    /**
     * Get popular articles.
     */
    public function popular(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = KnowledgeArticle::published()
            ->popular()
            ->with(['category:id,name', 'department:id,name'])
            ->select(['id', 'title', 'summary', 'category_id', 'department_id', 'view_count', 'published_at']);

        // Apply department scoping
        if ($user) {
            $userDepartmentId = $user->getAttribute('department_id');
            $query->forDepartment($userDepartmentId);
        } else {
            $query->public();
        }

        $articles = $query->limit($request->get('limit', 5))->get()->map(function ($article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'summary' => $article->summary,
                'category' => $article->category?->name,
                'department' => $article->department?->name,
                'view_count' => $article->view_count,
                'published_at' => $article->published_at->toISOString(),
            ];
        });

        return response()->json([
            'articles' => $articles,
        ]);
    }

    /**
     * Get recent articles.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = KnowledgeArticle::published()
            ->recent()
            ->with(['category:id,name', 'department:id,name'])
            ->select(['id', 'title', 'summary', 'category_id', 'department_id', 'view_count', 'published_at']);

        // Apply department scoping
        if ($user) {
            $userDepartmentId = $user->getAttribute('department_id');
            $query->forDepartment($userDepartmentId);
        } else {
            $query->public();
        }

        $articles = $query->limit($request->get('limit', 5))->get()->map(function ($article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'summary' => $article->summary,
                'category' => $article->category?->name,
                'department' => $article->department?->name,
                'view_count' => $article->view_count,
                'published_at' => $article->published_at->toISOString(),
            ];
        });

        return response()->json([
            'articles' => $articles,
        ]);
    }
}

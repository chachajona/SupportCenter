<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class UserFeedbackController extends Controller
{
    /**
     * Display a paginated listing of the feedback.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', UserFeedback::class);

        $feedback = UserFeedback::query()
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return response()->json($feedback);
    }

    /**
     * Store newly created feedback in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => 'required|string',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|string',
            'feature_area' => 'nullable|string',
        ]);

        $feedback = $request->user()->feedback()->create($data);

        return response()->json($feedback, 201);
    }

    /**
     * Display the specified feedback.
     */
    public function show(UserFeedback $feedback): JsonResponse
    {
        $this->authorize('view', $feedback);

        return response()->json($feedback);
    }

    /**
     * Update the specified feedback in storage.
     */
    public function update(Request $request, UserFeedback $feedback): JsonResponse
    {
        $this->authorize('update', $feedback);

        $data = $request->validate([
            'status' => 'sometimes|string',
            'priority' => 'sometimes|string',
            'description' => 'sometimes|string',
        ]);

        $feedback->update($data);

        return response()->json($feedback);
    }

    /**
     * Remove the specified feedback from storage.
     */
    public function destroy(UserFeedback $feedback): JsonResponse
    {
        $this->authorize('delete', $feedback);

        $feedback->delete();

        return response()->json(['message' => 'Feedback deleted']);
    }

    /**
     * Mark feedback as reviewed.
     */
    public function markReviewed(UserFeedback $feedback): JsonResponse
    {
        Gate::authorize('manage-feedback');

        $feedback->update(['status' => 'reviewed']);

        return response()->json($feedback);
    }

    /**
     * Mark feedback as implemented.
     */
    public function markImplemented(UserFeedback $feedback): JsonResponse
    {
        Gate::authorize('manage-feedback');

        $feedback->update(['status' => 'implemented']);

        return response()->json($feedback);
    }
}

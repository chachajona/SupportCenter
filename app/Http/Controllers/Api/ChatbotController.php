<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\AI\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly ChatbotService $chatbotService
    ) {
    }

    /**
     * Start a new conversation
     */
    public function startConversation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'channel' => 'sometimes|string|in:web_chat,mobile_app,widget,api',
            'user_id' => 'sometimes|integer|exists:users,id',
            'initial_message' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $conversation = Conversation::start([
                'user_id' => $request->get('user_id'),
                'channel' => $request->get('channel', 'web_chat'),
            ]);

            // Add initial system message
            $conversation->addMessage(
                'Hello! I\'m your AI support assistant. How can I help you today?',
                'bot'
            );

            // Process initial user message if provided
            $botResponse = null;
            if ($request->has('initial_message')) {
                $botResponse = $this->processUserMessage($conversation, $request->get('initial_message'));
            }

            return response()->json([
                'success' => true,
                'conversation' => [
                    'id' => $conversation->id,
                    'session_id' => $conversation->session_id,
                    'status' => $conversation->status,
                    'started_at' => $conversation->started_at->toISOString(),
                ],
                'initial_response' => $botResponse,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start conversation', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start conversation. Please try again.',
            ], 500);
        }
    }

    /**
     * Send a message to the chatbot
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|exists:conversations,session_id',
            'message' => 'required|string|max:1000',
            'user_id' => 'sometimes|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $conversation = Conversation::where('session_id', $request->get('session_id'))->firstOrFail();

            // Check if conversation is still active
            if (!in_array($conversation->status, ['active', 'idle'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'This conversation has ended or been escalated.',
                    'conversation_status' => $conversation->status,
                ], 400);
            }

            $userMessage = $request->get('message');
            $botResponse = $this->processUserMessage($conversation, $userMessage, $request->get('user_id'));

            return response()->json([
                'success' => true,
                'response' => $botResponse->toArray(),
                'conversation' => [
                    'id' => $conversation->id,
                    'session_id' => $conversation->session_id,
                    'status' => $conversation->status,
                    'total_messages' => $conversation->total_messages,
                    'average_confidence' => $conversation->average_confidence,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process chatbot message', [
                'error' => $e->getMessage(),
                'session_id' => $request->get('session_id'),
                'message' => $request->get('message'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process your message. Please try again.',
            ], 500);
        }
    }

    /**
     * Get conversation history
     */
    public function getConversationHistory(Request $request, string $sessionId): JsonResponse
    {
        try {
            $conversation = Conversation::where('session_id', $sessionId)
                ->with([
                    'messages' => function ($query) {
                        $query->orderBy('created_at');
                    },
                ])
                ->firstOrFail();

            $messages = $conversation->messages()->get()->map(function (ConversationMessage $message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'sender_type' => $message->sender_type,
                    'sender_name' => $message->getSenderName(),
                    'timestamp' => $message->created_at->toISOString(),
                    'confidence' => $message->confidence_score,
                    'intent' => $message->intent,
                    'knowledge_articles' => $message->knowledge_articles_referenced,
                    'suggested_actions' => $message->suggested_actions,
                ];
            });

            return response()->json([
                'success' => true,
                'conversation' => [
                    'id' => $conversation->id,
                    'session_id' => $conversation->session_id,
                    'status' => $conversation->status,
                    'started_at' => $conversation->started_at->toISOString(),
                    'total_messages' => $conversation->total_messages,
                    'average_confidence' => $conversation->average_confidence,
                ],
                'messages' => $messages,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get conversation history', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);
        }
    }

    /**
     * Escalate conversation to human agent
     */
    public function escalateToHuman(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|exists:conversations,session_id',
            'reason' => 'sometimes|string|max:500',
            'user_id' => 'sometimes|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $conversation = Conversation::where('session_id', $request->get('session_id'))->firstOrFail();

            // Check if conversation can be escalated
            if ($conversation->status === 'escalated') {
                return response()->json([
                    'success' => false,
                    'message' => 'This conversation has already been escalated.',
                    'ticket_id' => $conversation->escalated_to_ticket_id,
                ], 400);
            }

            if ($conversation->status === 'ended') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot escalate an ended conversation.',
                ], 400);
            }

            // Store escalation reason
            $reason = $request->get('reason', 'User requested human assistance');
            $conversation->update(['escalation_reason' => $reason]);

            // Get user if provided
            $user = $request->get('user_id') ? \App\Models\User::find($request->get('user_id')) : null;

            // Escalate to human agent
            $ticket = $this->chatbotService->escalateToHuman($conversation, $user);

            // Add system message about escalation
            $conversation->addMessage(
                "I've connected you with a human agent. A support ticket has been created (#{$ticket->id}) and you'll receive assistance shortly.",
                'system'
            );

            return response()->json([
                'success' => true,
                'message' => 'Conversation escalated to human agent successfully.',
                'ticket' => [
                    'id' => $ticket->id,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status->name,
                    'priority' => $ticket->priority->name,
                ],
                'conversation' => [
                    'id' => $conversation->id,
                    'session_id' => $conversation->session_id,
                    'status' => $conversation->status,
                    'escalated_at' => $conversation->escalated_at->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to escalate conversation', [
                'error' => $e->getMessage(),
                'session_id' => $request->get('session_id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to escalate conversation. Please try again.',
            ], 500);
        }
    }

    /**
     * End a conversation
     */
    public function endConversation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|exists:conversations,session_id',
            'reason' => 'sometimes|string|max:500',
            'rating' => 'sometimes|integer|min:1|max:5',
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
            $conversation = Conversation::where('session_id', $request->get('session_id'))->firstOrFail();

            // Add rating and feedback if provided
            if ($request->has('rating')) {
                $conversation->rate($request->get('rating'), $request->get('feedback'));
            }

            // End the conversation
            $reason = $request->get('reason', 'user_ended');
            $conversation->end($reason);

            // Add farewell message
            $conversation->addMessage(
                'Thank you for using our support system. Have a great day!',
                'bot'
            );

            return response()->json([
                'success' => true,
                'message' => 'Conversation ended successfully.',
                'conversation' => [
                    'id' => $conversation->id,
                    'session_id' => $conversation->session_id,
                    'status' => $conversation->status,
                    'duration_minutes' => $conversation->duration,
                    'total_messages' => $conversation->total_messages,
                    'average_confidence' => $conversation->average_confidence,
                    'ended_at' => $conversation->ended_at->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to end conversation', [
                'error' => $e->getMessage(),
                'session_id' => $request->get('session_id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to end conversation.',
            ], 500);
        }
    }

    /**
     * Get chatbot analytics for admin users
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        // Check if user has admin permissions
        if (!$request->user() || !$request->user()->hasPermissionTo('analytics.view_all')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions.',
            ], 403);
        }

        try {
            $dateRange = $request->get('days', 30);
            $since = now()->subDays($dateRange);

            $analytics = [
                'total_conversations' => Conversation::where('created_at', '>=', $since)->count(),
                'active_conversations' => Conversation::active()->count(),
                'escalated_conversations' => Conversation::escalated()->where('created_at', '>=', $since)->count(),
                'average_confidence' => Conversation::where('created_at', '>=', $since)->avg('average_confidence'),
                'average_duration' => Conversation::where('ended_at', '>=', $since)->avg('duration'),
                'satisfaction_rating' => Conversation::where('created_at', '>=', $since)
                    ->whereNotNull('user_satisfaction_rating')
                    ->avg('user_satisfaction_rating'),
                'total_messages' => ConversationMessage::where('created_at', '>=', $since)->count(),
                'bot_messages' => ConversationMessage::fromBot()->where('created_at', '>=', $since)->count(),
                'user_messages' => ConversationMessage::fromUser()->where('created_at', '>=', $since)->count(),
                'high_confidence_messages' => ConversationMessage::highConfidence()->where('created_at', '>=', $since)->count(),
                'low_confidence_messages' => ConversationMessage::lowConfidence()->where('created_at', '>=', $since)->count(),
            ];

            return response()->json([
                'success' => true,
                'analytics' => $analytics,
                'date_range' => "{$dateRange} days",
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get chatbot analytics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate analytics.',
            ], 500);
        }
    }

    /**
     * Process user message and get bot response
     */
    private function processUserMessage(Conversation $conversation, string $userMessage, ?int $userId = null): \App\Services\AI\ChatbotResponse
    {
        $startTime = microtime(true);

        // Add user message to conversation
        $conversation->addMessage($userMessage, 'user', $userId);

        // Get conversation context
        $context = [
            'conversation_id' => $conversation->id,
            'total_messages' => $conversation->total_messages,
            'average_confidence' => $conversation->average_confidence,
            'failed_attempts' => $this->countLowConfidenceMessages($conversation),
        ];

        // Process message with chatbot service
        $botResponse = $this->chatbotService->processMessage($userMessage, $context);

        $responseTime = (int) ((microtime(true) - $startTime) * 1000);

        // Add bot response to conversation
        ConversationMessage::createBotMessage(
            $conversation->id,
            $botResponse->message,
            $botResponse->intent,
            $botResponse->confidence,
            $botResponse->knowledgeArticles->pluck('id')->toArray(),
            $botResponse->suggestedActions,
            $responseTime
        );

        return $botResponse;
    }

    /**
     * Count low confidence messages in conversation
     */
    private function countLowConfidenceMessages(Conversation $conversation): int
    {
        return $conversation->messages()
            ->where('sender_type', 'bot')
            ->where('confidence_score', '<', 0.6)
            ->count();
    }
}

<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Services\ChatAvailabilityService;
use Escalated\Laravel\Services\ChatSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WidgetChatController extends Controller
{
    public function __construct(
        protected ChatSessionService $chatSessionService,
        protected ChatAvailabilityService $availabilityService
    ) {}

    /**
     * Check if chat is available for a department.
     */
    public function availability(Request $request): JsonResponse
    {
        if (! EscalatedSettings::getBool('chat_enabled', false)) {
            return response()->json(['available' => false, 'reason' => 'Chat is disabled.']);
        }

        $departmentId = $request->input('department_id') ? (int) $request->input('department_id') : null;
        $available = $this->availabilityService->isAvailable($departmentId);

        return response()->json(['available' => $available]);
    }

    /**
     * Start a new chat session.
     */
    public function start(Request $request): JsonResponse
    {
        if (! EscalatedSettings::getBool('chat_enabled', false)) {
            return response()->json(['message' => 'Chat is disabled.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'department_id' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ]);

        $result = $this->chatSessionService->startChat($validated);

        return response()->json($result, 201);
    }

    /**
     * Send a customer message.
     */
    public function message(string $sessionId, Request $request): JsonResponse
    {
        $session = ChatSession::where('customer_session_id', $sessionId)->firstOrFail();

        if ($session->status === ChatSessionStatus::Ended) {
            return response()->json(['message' => 'Chat has ended.'], 422);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $reply = $this->chatSessionService->sendMessage($session, $validated['body'], null, false);

        return response()->json(['message' => 'Message sent.', 'reply_id' => $reply->id]);
    }

    /**
     * Customer typing indicator.
     */
    public function typing(string $sessionId): JsonResponse
    {
        $session = ChatSession::where('customer_session_id', $sessionId)->firstOrFail();

        if ($session->status === ChatSessionStatus::Ended) {
            return response()->json(['message' => 'Chat has ended.'], 422);
        }

        $this->chatSessionService->updateTyping($session, false);

        return response()->json(['message' => 'Typing updated.']);
    }

    /**
     * Customer ends the chat.
     */
    public function end(string $sessionId): JsonResponse
    {
        $session = ChatSession::where('customer_session_id', $sessionId)->firstOrFail();

        if ($session->status === ChatSessionStatus::Ended) {
            return response()->json(['message' => 'Chat has already ended.'], 422);
        }

        $this->chatSessionService->endChat($session, 'customer');

        return response()->json(['message' => 'Chat ended.']);
    }

    /**
     * Rate a completed chat.
     */
    public function rate(string $sessionId, Request $request): JsonResponse
    {
        $session = ChatSession::where('customer_session_id', $sessionId)->firstOrFail();

        if ($session->status !== ChatSessionStatus::Ended) {
            return response()->json(['message' => 'Chat must be ended before rating.'], 422);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->chatSessionService->rateChat($session, $validated['rating'], $validated['comment'] ?? null);

        return response()->json(['message' => 'Rating submitted.']);
    }
}

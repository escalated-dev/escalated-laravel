<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Enums\ChatStatus;
use Escalated\Laravel\Models\AgentProfile;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Services\ChatSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ChatController extends Controller
{
    public function __construct(
        protected ChatSessionService $chatSessionService
    ) {}

    /**
     * List active chats for the current agent.
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = ChatSession::active()
            ->forAgent($request->user()->id)
            ->with(['ticket', 'agent'])
            ->latest('started_at')
            ->get();

        return response()->json(['sessions' => $sessions]);
    }

    /**
     * List waiting/unassigned chats (queue).
     */
    public function queue(): JsonResponse
    {
        $sessions = ChatSession::waiting()
            ->with(['ticket'])
            ->oldest('started_at')
            ->get();

        return response()->json(['sessions' => $sessions]);
    }

    /**
     * Accept a chat from the queue.
     */
    public function accept(ChatSession $session, Request $request): JsonResponse
    {
        if ($session->status !== ChatSessionStatus::Waiting) {
            return response()->json(['message' => 'Chat is not in the queue.'], 422);
        }

        $this->chatSessionService->assignAgent($session, $request->user()->id);

        return response()->json(['message' => 'Chat accepted.', 'session' => $session->fresh()]);
    }

    /**
     * End a chat session.
     */
    public function end(ChatSession $session, Request $request): JsonResponse
    {
        if ($session->status === ChatSessionStatus::Ended) {
            return response()->json(['message' => 'Chat is already ended.'], 422);
        }

        $this->chatSessionService->endChat($session, 'agent');

        return response()->json(['message' => 'Chat ended.']);
    }

    /**
     * Transfer a chat to another agent or department.
     */
    public function transfer(ChatSession $session, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
        ]);

        if (empty($validated['agent_id']) && empty($validated['department_id'])) {
            return response()->json(['message' => 'Provide an agent_id or department_id.'], 422);
        }

        $this->chatSessionService->transferChat(
            $session,
            $validated['agent_id'] ?? null,
            $validated['department_id'] ?? null
        );

        return response()->json(['message' => 'Chat transferred.', 'session' => $session->fresh()]);
    }

    /**
     * Set agent chat status (online/away/offline).
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:online,away,offline'],
        ]);

        $profile = AgentProfile::forUser($request->user()->id);
        $profile->update(['chat_status' => ChatStatus::from($validated['status'])]);

        return response()->json(['message' => 'Status updated.', 'chat_status' => $validated['status']]);
    }

    /**
     * Send a message in a chat session (creates a reply on the ticket).
     */
    public function message(ChatSession $session, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $reply = $this->chatSessionService->sendMessage(
            $session,
            $validated['body'],
            $request->user()->id,
            true
        );

        return response()->json(['message' => 'Message sent.', 'reply_id' => $reply->id]);
    }

    /**
     * Update the typing indicator for the agent.
     */
    public function typing(ChatSession $session, Request $request): JsonResponse
    {
        $this->chatSessionService->updateTyping($session, true);

        return response()->json(['message' => 'Typing updated.']);
    }
}

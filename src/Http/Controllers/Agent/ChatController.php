<?php

namespace Escalated\Laravel\Http\Controllers\Agent;

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Http\Controllers\Admin\ChatController as AdminChatController;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Support\AgentChats;
use Escalated\Laravel\Support\StaffAccess;
use Escalated\Laravel\Support\TicketVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Live chat for agents (the escalated.agent.chat.* routes). The actions are
 * the admin ones (escalated.admin.chat.*), limited to the chats an agent may
 * run: any agent who can see the ticket may accept a waiting chat; ending,
 * transferring, sending and typing are for the agent the chat is assigned to,
 * and for admins.
 */
class ChatController extends AdminChatController
{
    /**
     * The agent's own active chats, in the shape the agent panel reads.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['sessions' => AgentChats::active($request->user())]);
    }

    /**
     * Chats waiting for an agent, oldest first.
     */
    public function queue(): JsonResponse
    {
        return response()->json(['sessions' => AgentChats::waiting()]);
    }

    public function accept(ChatSession $session, Request $request): JsonResponse
    {
        $this->ensureCanSee($session, $request);

        return parent::accept($session, $request);
    }

    public function end(ChatSession $session, Request $request): JsonResponse
    {
        $this->ensureHandles($session, $request);

        return parent::end($session, $request);
    }

    public function transfer(ChatSession $session, Request $request): JsonResponse
    {
        $this->ensureHandles($session, $request);

        if ($session->status === ChatSessionStatus::Ended) {
            return response()->json(['message' => 'Chat has ended.'], 422);
        }

        $validated = $request->validate([
            'agent_id' => ['nullable'],
            'department_id' => ['nullable', 'integer', 'exists:'.Department::class.',id'],
        ]);

        $agentId = $validated['agent_id'] ?? null;
        $departmentId = $validated['department_id'] ?? null;

        if (($agentId === null || $agentId === '') && empty($departmentId)) {
            return response()->json(['message' => 'Provide an agent_id or department_id.'], 422);
        }

        if ($agentId !== null && $agentId !== '') {
            // Only to staff who can see the ticket.
            $target = Escalated::findUser($agentId);
            if (! $target || ! StaffAccess::isStaff($target) || ! TicketVisibility::agentCanAccess($target->getKey(), $session)) {
                throw ValidationException::withMessages(['agent_id' => 'That agent cannot take this chat.']);
            }
            $agentId = $target->getKey();
        } else {
            $agentId = null;
        }

        $this->chatSessionService->transferChat($session, $agentId, $departmentId);

        return response()->json(['message' => 'Chat transferred.', 'session' => $session->fresh()]);
    }

    public function message(ChatSession $session, Request $request): JsonResponse
    {
        $this->ensureHandles($session, $request);

        if ($session->status === ChatSessionStatus::Ended) {
            return response()->json(['message' => 'Chat has ended.'], 422);
        }

        return parent::message($session, $request);
    }

    public function typing(ChatSession $session, Request $request): JsonResponse
    {
        $this->ensureHandles($session, $request);

        if ($session->status === ChatSessionStatus::Ended) {
            return response()->json(['message' => 'Chat has ended.'], 422);
        }

        return parent::typing($session, $request);
    }

    /** The agent can see the chat's ticket (tenancy and visibility rules). */
    protected function ensureCanSee(ChatSession $session, Request $request): void
    {
        $ticket = $session->ticket;

        abort_unless($ticket && Gate::forUser($request->user())->allows('reply', $ticket), 404);
    }

    /** The chat is the agent's own, or the agent is an admin. */
    protected function ensureHandles(ChatSession $session, Request $request): void
    {
        $this->ensureCanSee($session, $request);

        $user = $request->user();
        $own = $session->agent_id !== null && (string) $session->agent_id === (string) $user->getAuthIdentifier();

        abort_unless($own || StaffAccess::isAdmin($user), 403, 'This chat is not assigned to you.');
    }
}

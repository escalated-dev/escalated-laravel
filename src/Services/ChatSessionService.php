<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Enums\TicketChannel;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Events\ChatAssigned;
use Escalated\Laravel\Events\ChatEnded;
use Escalated\Laravel\Events\ChatMessage;
use Escalated\Laravel\Events\ChatStarted;
use Escalated\Laravel\Events\ChatTransferred;
use Escalated\Laravel\Events\ChatTyping;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\TenantContext;

class ChatSessionService
{
    public function __construct(
        protected ChatRoutingService $routingService
    ) {}

    /**
     * Start a new chat session. Creates a ticket (status: live, channel: chat) and a chat session (status: waiting).
     */
    public function startChat(array $data): array
    {
        return app(GuestEmailVerification::class)->consume(
            $data['verification_id'] ?? '', $data['verification_code'] ?? '', $data['email'] ?? '', 'chat',
            fn () => $this->startVerifiedChat($data)
        );
    }

    protected function startVerifiedChat(array $data): array
    {
        $email = GuestEmailVerification::email($data['email']);
        $contact = Contact::findOrCreateByEmail($email, $data['name']);
        $ticket = new Ticket([
            'subject' => $data['subject'] ?? 'Live Chat',
            'description' => $data['message'] ?? '',
            'status' => TicketStatus::Live,
            'priority' => TicketPriority::from($data['priority'] ?? config('escalated.default_priority', 'medium')),
            'channel' => TicketChannel::Chat,
            'department_id' => $data['department_id'] ?? null,
            'guest_name' => $data['name'] ?? null,
            'guest_email' => $email,
            'guest_verified_email' => $email,
            'guest_email_verified_at' => now(),
            'contact_id' => $contact->id,
            'chat_metadata' => $data['metadata'] ?? null,
        ]);
        $ticket->deferCreatedEvent = true;
        app(GuestTicketService::class)->applyIdentityPolicy($ticket);
        $ticket->save();
        $token = app(GuestAccess::class)->issue($ticket, 'chat');

        $session = ChatSession::create([
            'ticket_id' => $ticket->id,
            'customer_session_id' => hash('sha256', $token),
            'status' => ChatSessionStatus::Waiting,
            'started_at' => now(),
            'metadata' => $data['metadata'] ?? null,
        ]);

        $ticket->dispatchCreatedAfterCommit();
        $tenant = app(TenantContext::class)->enabled() ? (string) $ticket->tenant_id : null;
        Escalated::db()->afterCommit(function () use ($session, $tenant) {
            $route = function () use ($session) {
                $session->refresh();
                $this->routingService->evaluateRouting($session);
                ChatStarted::dispatch($session->fresh());
            };
            if ($tenant !== null) {
                app(TenantContext::class)->run($tenant, $route);
            } else {
                $route();
            }
        });

        return [
            'id' => $token,
            'session_id' => $token,
            'expires_at' => $ticket->guest_access_expires_at->toIso8601String(),
            'ticket_reference' => $ticket->reference,
            'status' => $session->status->value,
            'agent_name' => $session->agent?->name,
        ];
    }

    /**
     * Assign an agent to a chat session.
     */
    public function assignAgent(ChatSession $session, int|string $agentId): void
    {
        $session->update([
            'agent_id' => $agentId,
            'status' => ChatSessionStatus::Active,
        ]);

        $session->ticket->update(['assigned_to' => $agentId]);

        $session->refresh();

        ChatAssigned::dispatch($session);
    }

    /**
     * End a chat session and close the ticket.
     */
    public function endChat(ChatSession $session, string $endedBy = 'system'): void
    {
        $session->update([
            'status' => ChatSessionStatus::Ended,
            'ended_at' => now(),
        ]);

        $ticket = $session->ticket;
        $ticket->update([
            'status' => TicketStatus::Closed,
            'closed_at' => now(),
            'chat_ended_at' => now(),
        ]);

        ChatEnded::dispatch($session, $endedBy);
    }

    /**
     * Transfer a chat to another agent or department.
     */
    public function transferChat(ChatSession $session, ?int $toAgentId = null, ?int $toDepartmentId = null): void
    {
        $fromAgentId = $session->agent_id;

        $updates = [];

        if ($toAgentId) {
            $updates['agent_id'] = $toAgentId;
            $session->ticket->update(['assigned_to' => $toAgentId]);
        }

        if ($toDepartmentId) {
            $session->ticket->update(['department_id' => $toDepartmentId]);

            if (! $toAgentId) {
                $updates['agent_id'] = null;
                $updates['status'] = ChatSessionStatus::Waiting;
                $session->ticket->update(['assigned_to' => null]);
            }
        }

        $session->update($updates);
        $session->refresh();

        ChatTransferred::dispatch($session, $fromAgentId, $toAgentId, $toDepartmentId);

        if (! $toAgentId && $toDepartmentId) {
            $this->routingService->evaluateRouting($session);
        }
    }

    /**
     * Send a message in a chat session (creates a reply on the ticket).
     */
    public function sendMessage(ChatSession $session, string $body, int|string|null $userId, bool $isAgent): Reply
    {
        $replyData = [
            'ticket_id' => $session->ticket_id,
            'body' => $body,
            'is_internal_note' => false,
            'type' => 'reply',
        ];

        if ($userId) {
            $userModel = Escalated::userModel();
            $user = Escalated::findUser($userId);

            if ($user) {
                $replyData['author_type'] = $user->getMorphClass();
                $replyData['author_id'] = $user->getKey();
            }
        }

        $reply = Reply::create($replyData);

        ChatMessage::dispatch($session, $reply, $isAgent);

        return $reply;
    }

    /**
     * Update the typing indicator for a chat session.
     */
    public function updateTyping(ChatSession $session, bool $isAgent): void
    {
        $column = $isAgent ? 'agent_typing_at' : 'customer_typing_at';
        $session->update([$column => now()]);

        $userName = null;
        if ($isAgent && $session->agent_id) {
            $userName = $session->agent?->name;
        }

        ChatTyping::dispatch($session, $isAgent, $userName);
    }

    /**
     * Rate a completed chat session.
     */
    public function rateChat(ChatSession $session, int $rating, ?string $comment = null): void
    {
        $session->update([
            'rating' => $rating,
            'rating_comment' => $comment,
        ]);
    }
}

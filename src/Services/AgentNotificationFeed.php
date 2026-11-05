<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Notifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * The agent notifications feed (ADR 2026-11-05-agent-panel-data, section 4).
 *
 * It reads Laravel's database notifications (the host's `notifications`
 * table), which Escalated already writes through the `database` channel, and
 * lists only Escalated's own notification classes.
 */
class AgentNotificationFeed
{
    /** Notification class => feed type. */
    public const TYPES = [
        Notifications\TicketAssignedNotification::class => 'ticket_assigned',
        Notifications\TicketReplyNotification::class => 'ticket_reply',
        Notifications\MentionNotification::class => 'mention',
        Notifications\SlaWarningNotification::class => 'sla_warning',
        Notifications\SlaBreachNotification::class => 'sla_breached',
        Notifications\ChatTransferredNotification::class => 'chat_transferred',
        Notifications\NewTicketNotification::class => 'new_ticket',
        Notifications\TicketEscalatedNotification::class => 'ticket_escalated',
        Notifications\TicketStatusChangedNotification::class => 'status_changed',
    ];

    public const PER_PAGE = 20;

    public const MAX_PER_PAGE = 50;

    /** In-app delivery is on and the host has the notifications table. */
    public function available(mixed $user = null): bool
    {
        if (! in_array('database', (array) config('escalated.notifications.channels', ['mail', 'database']), true)) {
            return false;
        }

        if ($user !== null && ! ($user instanceof Model && method_exists($user, 'notifications'))) {
            return false;
        }

        try {
            $model = $user instanceof Model ? $user : Escalated::newUserModel();

            return $model->getConnection()->getSchemaBuilder()->hasTable('notifications');
        } catch (\Throwable) {
            return false;
        }
    }

    public function query(Model $user): Builder
    {
        return (new DatabaseNotification)->setConnection($user->getConnectionName())->newQuery()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->whereIn('type', array_keys(self::TYPES));
    }

    public function unreadCount(Model $user): int
    {
        return $this->query($user)->whereNull('read_at')->count();
    }

    /**
     * The feed items, with tickets the agent can no longer view redacted.
     *
     * @param  iterable<DatabaseNotification>  $notifications
     * @return list<array<string, mixed>>
     */
    public function present(iterable $notifications): array
    {
        $notifications = Collection::make($notifications);
        $ids = $notifications->map(fn (DatabaseNotification $n) => $n->data['ticket_id'] ?? null)->filter()->unique()->values();

        // Through the ticket visibility scope, as the signed-in agent.
        $tickets = $ids->isEmpty() ? collect() : Ticket::query()->whereIn('id', $ids->all())->get(['id', 'reference', 'subject'])->keyBy('id');

        return $notifications->map(fn (DatabaseNotification $n) => $this->item($n, $tickets))->values()->all();
    }

    /** @param  Collection<int|string, Ticket>  $tickets */
    public function item(DatabaseNotification $notification, Collection $tickets): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $type = self::TYPES[$notification->type] ?? 'generic';
        $ticketId = $data['ticket_id'] ?? null;
        $ticket = $ticketId !== null ? $tickets->get($ticketId) : null;

        if ($ticketId !== null && $ticket === null) {
            return $this->shape($notification, $type, __('escalated::messages.agent_notifications.unavailable'), null, null);
        }

        $title = __('escalated::messages.agent_notifications.'.$type, [
            'reference' => $ticket?->reference ?? ($data['reference'] ?? ''),
            'name' => $data['mentioned_by'] ?? '',
        ]);

        return $this->shape($notification, $type, $title, $ticket?->subject, $ticket);
    }

    private function shape(DatabaseNotification $notification, string $type, string $title, ?string $body, ?Ticket $ticket): array
    {
        return [
            'id' => (string) $notification->getKey(),
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'ticket' => $ticket ? ['id' => $ticket->id, 'reference' => $ticket->reference, 'subject' => $ticket->subject] : null,
            'url' => $ticket && Route::has('escalated.agent.tickets.show') ? route('escalated.agent.tickets.show', $ticket->reference) : null,
            'created_at' => $notification->created_at?->toIso8601String(),
            'read_at' => $notification->read_at?->toIso8601String(),
        ];
    }
}

<?php

namespace Escalated\Laravel\Http\Controllers\Agent;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\TicketCustomActionTriggered;
use Escalated\Laravel\Http\Requests\AssignTicketRequest;
use Escalated\Laravel\Http\Requests\ChangePriorityRequest;
use Escalated\Laravel\Http\Requests\ChangeStatusRequest;
use Escalated\Laravel\Http\Requests\ReplyToTicketRequest;
use Escalated\Laravel\Http\Requests\SnoozeTicketRequest;
use Escalated\Laravel\Http\Requests\UpdateTagsRequest;
use Escalated\Laravel\Http\Requests\UpdateTicketRequest;
use Escalated\Laravel\Models\CannedResponse;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Macro;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AgentInbox;
use Escalated\Laravel\Services\AssignmentService;
use Escalated\Laravel\Services\MacroService;
use Escalated\Laravel\Services\TicketActionRegistry;
use Escalated\Laravel\Services\TicketReads;
use Escalated\Laravel\Services\TicketService;
use Escalated\Laravel\Support\AssignableAgents;
use Escalated\Laravel\Support\TicketAccounts;
use Escalated\Laravel\Support\TicketListFilters;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected AssignmentService $assignmentService,
        protected EscalatedUiRenderer $renderer,
        protected TicketActionRegistry $ticketActions,
    ) {}

    public function index(Request $request, AgentInbox $inbox, TicketReads $reads): mixed
    {
        $request->validate(array_merge(TicketAccounts::filterRules(), TicketListFilters::rules()));
        $agent = $request->user();

        $filters = $inbox->normaliseFilters($request->only([
            'status', 'priority', 'ticket_type', 'assigned', 'assigned_to', 'unassigned', 'department_id',
            'account_type', 'account_id', 'search', 'sla_breached', 'sla_at_risk', 'resolved_today', 'open',
            'updated_within_hours', 'tag_ids', 'sort_by', 'sort_dir', 'per_page', 'following', 'view',
        ]), $agent);

        $queue = $inbox->queueFor($filters, $agent);
        $inbox->remember($request, $queue);

        $tickets = $this->ticketService->list(
            $inbox->listFilters($filters, $queue),
            $request->has('following') ? $agent : null,
        );

        if ($inbox->tracksLocally()) {
            $flags = $reads->flags($agent, $tickets->getCollection());
            $tickets->getCollection()->each(fn (Ticket $ticket) => $ticket->setAttribute('is_unread', $flags[$ticket->getKey()]));
        }

        return $this->renderer->render('Escalated/Agent/TicketIndex', [
            'tickets' => $tickets,
            'filters' => $request->all(),
            'departments' => Department::active()->get(['id', 'name']),
            'tags' => Tag::all(['id', 'name', 'color']),
            'queue' => $inbox->queueProp($queue),
        ] + $inbox->inboxProp($agent, $queue));
    }

    public function show(Ticket $ticket, Request $request, AgentInbox $inbox, TicketReads $reads): mixed
    {
        // Opening the ticket in the agent panel is reading it.
        if ($inbox->tracksLocally()) {
            $reads->markRead($request->user(), $ticket);
            $ticket->setAttribute('is_unread', false);
        }

        $ticket->load([
            'replies' => fn ($q) => $q->with('author', 'attachments')->latest(),
            'attachments', 'tags', 'department', 'requester', 'assignee',
            'slaPolicy', 'activities' => fn ($q) => $q->with('causer')->latest()->take(20),
            'satisfactionRating', 'pinnedNotes.author', 'chatSession',
            'linksAsParent.childTicket', 'linksAsChild.parentTicket',
        ]);

        $ticket->append(['chat_session_id', 'chat_started_at', 'chat_messages', 'requester_ticket_count', 'related_tickets']);
        $ticket->setAttribute('requester_tickets', $this->requesterTickets($ticket));

        $queue = $inbox->recalled($request, $request->user());

        return $this->renderer->render('Escalated/Agent/TicketShow', [
            'ticket' => $ticket,
            'departments' => Department::active()->get(['id', 'name']),
            'tags' => Tag::all(['id', 'name', 'color']),
            'cannedResponses' => CannedResponse::forAgent($request->user()->getKey())->get(),
            'agents' => AssignableAgents::for($ticket),
            'macros' => Macro::forAgent($request->user()->getKey())->orderBy('order')->get(),
            'is_following' => $ticket->isFollowedBy($request->user()->getKey()),
            'followers_count' => $ticket->followers()->count(),
            'customActions' => $this->customActionsForTicket($ticket, $request),
            'queue' => $inbox->queueProp($queue),
        ] + $inbox->inboxProp($request->user(), $queue));
    }

    public function update(Ticket $ticket, UpdateTicketRequest $request): RedirectResponse
    {
        $this->ticketService->update($ticket, $request->validated());

        return back()->with('success', __('escalated::messages.ticket.updated'));
    }

    public function reply(Ticket $ticket, ReplyToTicketRequest $request): RedirectResponse
    {
        $this->ticketService->reply($ticket, $request->user(), $request->validated('body'), $request->file('attachments', []));

        return back()->with('success', __('escalated::messages.ticket.reply_sent'));
    }

    public function note(Ticket $ticket, ReplyToTicketRequest $request): RedirectResponse
    {
        $this->ticketService->addNote($ticket, $request->user(), $request->validated('body'), $request->file('attachments', []));

        return back()->with('success', __('escalated::messages.ticket.note_added'));
    }

    public function assign(Ticket $ticket, AssignTicketRequest $request): RedirectResponse
    {
        $this->assignmentService->assign($ticket, $request->validated('agent_id'), $request->user());

        return back()->with('success', __('escalated::messages.ticket.assigned'));
    }

    public function status(Ticket $ticket, ChangeStatusRequest $request): RedirectResponse
    {
        $this->ticketService->changeStatus($ticket, TicketStatus::from($request->validated('status')), $request->user());

        return back()->with('success', __('escalated::messages.ticket.status_updated'));
    }

    public function priority(Ticket $ticket, ChangePriorityRequest $request): RedirectResponse
    {
        $this->ticketService->changePriority($ticket, TicketPriority::from($request->validated('priority')), $request->user());

        return back()->with('success', __('escalated::messages.ticket.priority_updated'));
    }

    public function tags(Ticket $ticket, UpdateTagsRequest $request): RedirectResponse
    {
        $newTagIds = collect($request->validated('tag_ids'))->map(fn ($id) => (int) $id);
        $currentTagIds = $ticket->tags()->pluck('id');

        $toAdd = $newTagIds->diff($currentTagIds)->values()->all();
        $toRemove = $currentTagIds->diff($newTagIds)->values()->all();

        if ($toAdd) {
            $this->ticketService->addTags($ticket, $toAdd, $request->user());
        }
        if ($toRemove) {
            $this->ticketService->removeTags($ticket, $toRemove, $request->user());
        }

        return back()->with('success', __('escalated::messages.ticket.tags_updated'));
    }

    public function department(Ticket $ticket, Request $request): RedirectResponse
    {
        $request->validate(['department_id' => 'required|integer']);

        $this->ticketService->changeDepartment($ticket, $request->integer('department_id'), $request->user());

        return back()->with('success', __('escalated::messages.ticket.department_updated'));
    }

    public function applyMacro(Ticket $ticket, Request $request, MacroService $macroService): RedirectResponse
    {
        $request->validate(['macro_id' => 'required|integer']);

        $macro = Macro::forAgent($request->user()->getKey())->findOrFail($request->integer('macro_id'));
        $macroService->apply($macro, $ticket, $request->user());

        return back()->with('success', __('escalated::messages.ticket.macro_applied', ['name' => $macro->name]));
    }

    public function customAction(Ticket $ticket, string $action, Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'payload' => 'sometimes|array',
        ]);

        $ticketAction = $this->ticketActions->find($action);

        abort_unless($ticketAction, 404);
        abort_unless($ticketAction->visible($ticket, $request->user()), 404);
        abort_unless($ticketAction->enabled($ticket, $request->user()), 403);

        TicketCustomActionTriggered::dispatch(
            $ticket,
            $ticketAction->key(),
            $request->user(),
            $validated['payload'] ?? [],
            $ticketAction->metadata($ticket, $request->user()),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('escalated::messages.ticket.custom_action_dispatched'),
                'action' => $ticketAction->key(),
            ]);
        }

        return back()->with('success', __('escalated::messages.ticket.custom_action_dispatched'));
    }

    public function markRead(Ticket $ticket, Request $request, TicketReads $reads): RedirectResponse|JsonResponse
    {
        $reads->markRead($request->user(), $ticket);

        return $this->readAnswer($request, false);
    }

    public function markUnread(Ticket $ticket, Request $request, TicketReads $reads): RedirectResponse|JsonResponse
    {
        $reads->markUnread($request->user(), $ticket);

        return $this->readAnswer($request, true);
    }

    private function readAnswer(Request $request, bool $unread): RedirectResponse|JsonResponse
    {
        return $request->wantsJson() && ! $request->header('X-Inertia')
            ? response()->json(['is_unread' => $unread])
            : back();
    }

    public function follow(Ticket $ticket, Request $request): RedirectResponse
    {
        $userId = $request->user()->getKey();

        if ($ticket->isFollowedBy($userId)) {
            $ticket->unfollow($userId);

            return back()->with('success', __('escalated::messages.ticket.unfollowed'));
        }

        $ticket->follow($userId);

        return back()->with('success', __('escalated::messages.ticket.following'));
    }

    public function presence(Ticket $ticket, Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('addNote', $ticket);
        $userId = $request->user()->getKey();
        $userName = $request->user()->name;
        $cacheKey = app(TenantContext::class)->cacheKey("escalated.presence.{$ticket->id}.{$userId}");

        Cache::put($cacheKey, ['id' => $userId, 'name' => $userName], 60);

        $viewers = [];

        // Collect all viewers from cache
        foreach (Cache::get(app(TenantContext::class)->cacheKey("escalated.presence_list.{$ticket->id}"), []) as $uid) {
            if ($uid !== $userId && Cache::has(app(TenantContext::class)->cacheKey("escalated.presence.{$ticket->id}.{$uid}"))) {
                $viewers[] = Cache::get(app(TenantContext::class)->cacheKey("escalated.presence.{$ticket->id}.{$uid}"));
            }
        }

        // Track active user IDs
        $activeIds = Cache::get(app(TenantContext::class)->cacheKey("escalated.presence_list.{$ticket->id}"), []);
        if (! in_array($userId, $activeIds)) {
            $activeIds[] = $userId;
        }
        Cache::put(app(TenantContext::class)->cacheKey("escalated.presence_list.{$ticket->id}"), $activeIds, 120);

        return response()->json(['viewers' => $viewers]);
    }

    public function snooze(Ticket $ticket, SnoozeTicketRequest $request): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('update', $ticket);
        $until = Carbon::parse($request->validated('snoozed_until'));

        $this->ticketService->snoozeTicket($ticket, $until, $request->user());

        return back()->with('success', 'Ticket snoozed until '.$until->format('M j, Y g:ia').'.');
    }

    public function unsnooze(Ticket $ticket, Request $request): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('update', $ticket);
        $this->ticketService->unsnoozeTicket($ticket, $request->user());

        return back()->with('success', 'Ticket unsnoozed.');
    }

    public function pin(Ticket $ticket, Reply $reply, Request $request): RedirectResponse
    {
        if (! $reply->is_internal_note) {
            return back()->with('error', __('escalated::messages.ticket.only_internal_notes_pinned'));
        }

        $reply->update(['is_pinned' => ! $reply->is_pinned]);

        return back()->with('success', $reply->is_pinned ? 'Note pinned.' : 'Note unpinned.');
    }

    /**
     * The requester's five most recent other tickets the agent may see
     * (the visibility scope applies), matched as requester_ticket_count is.
     *
     * @return list<array<string, mixed>>
     */
    protected function requesterTickets(Ticket $ticket): array
    {
        $query = Ticket::query()->whereKeyNot($ticket->getKey());

        if ($ticket->isGuest()) {
            if (! $ticket->guest_email) {
                return [];
            }
            $query->where('guest_email', $ticket->guest_email);
        } elseif ($ticket->requester_type && $ticket->requester_id) {
            $query->where('requester_type', $ticket->requester_type)
                ->where('requester_id', $ticket->requester_id);
        } else {
            return [];
        }

        return $query->latest('created_at')->latest('id')->limit(5)
            ->get(['id', 'reference', 'subject', 'status', 'created_at', 'updated_at'])
            ->map(fn (Ticket $other) => [
                'id' => $other->id,
                'reference' => $other->reference,
                'subject' => $other->subject,
                'status' => $other->status instanceof \BackedEnum ? $other->status->value : $other->status,
                'created_at' => $other->created_at?->toIso8601String(),
                'updated_at' => $other->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    protected function customActionsForTicket(Ticket $ticket, Request $request): array
    {
        return collect($this->ticketActions->forTicket($ticket, $request->user()))
            ->map(fn (array $action) => array_merge($action, [
                'url' => route('escalated.agent.tickets.custom-action', [$ticket->reference, $action['key']]),
                'method' => 'post',
            ]))
            ->values()
            ->all();
    }
}

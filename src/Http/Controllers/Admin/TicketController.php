<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Http\Requests\AssignTicketRequest;
use Escalated\Laravel\Http\Requests\ChangePriorityRequest;
use Escalated\Laravel\Http\Requests\ChangeStatusRequest;
use Escalated\Laravel\Http\Requests\ReplyToTicketRequest;
use Escalated\Laravel\Http\Requests\SnoozeTicketRequest;
use Escalated\Laravel\Http\Requests\UpdateTagsRequest;
use Escalated\Laravel\Models\CannedResponse;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Macro;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AssignmentService;
use Escalated\Laravel\Services\MacroService;
use Escalated\Laravel\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected AssignmentService $assignmentService,
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(Request $request): mixed
    {
        $tickets = $this->ticketService->list(
            $request->only(['status', 'priority', 'ticket_type', 'assigned_to', 'unassigned', 'department_id', 'search', 'sla_breached', 'tag_ids', 'sort_by', 'sort_dir', 'per_page', 'following', 'snoozed']),
            $request->has('following') ? $request->user() : null,
        );

        return $this->renderer->render('Escalated/Admin/Tickets/Index', [
            'tickets' => $tickets,
            'filters' => $request->all(),
            'departments' => Department::active()->get(['id', 'name']),
            'tags' => Tag::all(['id', 'name', 'color']),
            'agents' => $this->getAgents(),
        ]);
    }

    public function show(Ticket $ticket, Request $request): mixed
    {
        $ticket->load([
            'replies' => fn ($q) => $q->with('author', 'attachments')->latest(),
            'attachments', 'tags', 'department', 'requester', 'assignee',
            'slaPolicy', 'activities' => fn ($q) => $q->with('causer')->latest()->take(20),
            'satisfactionRating', 'pinnedNotes.author',
        ]);

        return $this->renderer->render('Escalated/Admin/Tickets/Show', [
            'ticket' => $ticket,
            'departments' => Department::active()->get(['id', 'name']),
            'tags' => Tag::all(['id', 'name', 'color']),
            'cannedResponses' => CannedResponse::forAgent($request->user()->getKey())->get(),
            'agents' => $this->getAgents(),
            'macros' => Macro::forAgent($request->user()->getKey())->orderBy('order')->get(),
            'is_following' => $ticket->isFollowedBy($request->user()->getKey()),
            'followers_count' => $ticket->followers()->count(),
            'is_snoozed' => $ticket->is_snoozed,
        ]);
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

    public function presence(Ticket $ticket, Request $request): \Illuminate\Http\JsonResponse
    {
        $userId = $request->user()->getKey();
        $userName = $request->user()->name;
        $cacheKey = "escalated.presence.{$ticket->id}.{$userId}";

        Cache::put($cacheKey, ['id' => $userId, 'name' => $userName], 60);

        $viewers = [];

        foreach (Cache::get("escalated.presence_list.{$ticket->id}", []) as $uid) {
            if ($uid !== $userId && Cache::has("escalated.presence.{$ticket->id}.{$uid}")) {
                $viewers[] = Cache::get("escalated.presence.{$ticket->id}.{$uid}");
            }
        }

        $activeIds = Cache::get("escalated.presence_list.{$ticket->id}", []);
        if (! in_array($userId, $activeIds)) {
            $activeIds[] = $userId;
        }
        Cache::put("escalated.presence_list.{$ticket->id}", $activeIds, 120);

        return response()->json(['viewers' => $viewers]);
    }

    public function pin(Ticket $ticket, Reply $reply, Request $request): RedirectResponse
    {
        if (! $reply->is_internal_note) {
            return back()->with('error', __('escalated::messages.ticket.only_internal_notes_pinned'));
        }

        $reply->update(['is_pinned' => ! $reply->is_pinned]);

        return back()->with('success', $reply->is_pinned ? 'Note pinned.' : 'Note unpinned.');
    }

    public function snooze(Ticket $ticket, SnoozeTicketRequest $request): RedirectResponse
    {
        $until = Carbon::parse($request->validated('snoozed_until'));

        $this->ticketService->snoozeTicket($ticket, $until, $request->user());

        return back()->with('success', 'Ticket snoozed until '.$until->format('M j, Y g:ia').'.');
    }

    public function unsnooze(Ticket $ticket, Request $request): RedirectResponse
    {
        $this->ticketService->unsnoozeTicket($ticket, $request->user());

        return back()->with('success', 'Ticket unsnoozed.');
    }

    protected function getAgents(): array
    {
        $userModel = Escalated::userModel();
        $users = $userModel::all();

        return $users->filter(function ($user) {
            return (method_exists($user, 'escalated_agent') && $user->escalated_agent())
                || (method_exists($user, 'escalated_admin') && $user->escalated_admin());
        })->map(fn ($user) => [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
        ])->values()->all();
    }
}

<?php

namespace Escalated\Laravel\Http\Controllers\Api;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Http\Resources\TicketCollectionResource;
use Escalated\Laravel\Http\Resources\TicketResource;
use Escalated\Laravel\Models\Macro;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AssignmentService;
use Escalated\Laravel\Services\MacroService;
use Escalated\Laravel\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected AssignmentService $assignmentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $filters = $request->only(['status', 'priority', 'assigned_to', 'unassigned', 'department_id', 'search', 'sla_breached', 'tag_ids', 'sort_by', 'sort_dir', 'per_page', 'following']);
        $filters['per_page'] = min((int) ($filters['per_page'] ?? 15), 100);

        $tickets = $this->ticketService->list(
            $filters,
            $request->has('following') ? $request->user() : null,
        );

        return response()->json([
            'data' => TicketCollectionResource::collection($tickets),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $ticket->load([
            'replies' => fn ($q) => $q->with('author', 'attachments')->latest(),
            'attachments', 'tags', 'department', 'requester', 'assignee',
            'slaPolicy', 'activities' => fn ($q) => $q->with('causer')->latest()->take(20),
            'satisfactionRating', 'pinnedNotes.author',
        ]);

        return response()->json(['data' => new TicketResource($ticket)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:65535',
            'priority' => 'sometimes|string|in:low,medium,high,urgent,critical',
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Escalated::table('departments'), 'id')],
            'tags' => 'sometimes|array',
            'tags.*' => ['integer', Rule::exists(Escalated::table('tags'), 'id')],
        ]);

        $ticket = $this->ticketService->create($request->user(), $validated);

        return response()->json([
            'data' => new TicketResource($ticket->load(['requester', 'assignee', 'department', 'tags'])),
            'message' => 'Ticket created.',
        ], 201);
    }

    public function reply(Ticket $ticket, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:65535',
            'is_internal_note' => 'sometimes|boolean',
        ]);

        $isNote = $validated['is_internal_note'] ?? false;

        if ($isNote) {
            $reply = $this->ticketService->addNote($ticket, $request->user(), $validated['body']);
        } else {
            $reply = $this->ticketService->reply($ticket, $request->user(), $validated['body']);
        }

        return response()->json([
            'data' => [
                'id' => $reply->id,
                'body' => $reply->body,
                'is_internal_note' => $reply->is_internal_note,
                'author' => ['id' => $request->user()->getKey(), 'name' => $request->user()->name],
                'created_at' => $reply->created_at->toIso8601String(),
            ],
            'message' => $isNote ? 'Note added.' : 'Reply sent.',
        ], 201);
    }

    public function status(Ticket $ticket, Request $request): JsonResponse
    {
        $validStatuses = array_column(TicketStatus::cases(), 'value');

        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', $validStatuses),
        ]);

        $this->ticketService->changeStatus($ticket, TicketStatus::from($validated['status']), $request->user());

        return response()->json(['message' => 'Status updated.', 'status' => $validated['status']]);
    }

    public function priority(Ticket $ticket, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'priority' => 'required|string|in:low,medium,high,urgent,critical',
        ]);

        $this->ticketService->changePriority($ticket, TicketPriority::from($validated['priority']), $request->user());

        return response()->json(['message' => 'Priority updated.', 'priority' => $validated['priority']]);
    }

    public function assign(Ticket $ticket, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|integer|exists:users,id',
        ]);

        $this->assignmentService->assign($ticket, $validated['agent_id'], $request->user());

        return response()->json(['message' => 'Ticket assigned.']);
    }

    public function follow(Ticket $ticket, Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        if ($ticket->isFollowedBy($userId)) {
            $ticket->unfollow($userId);

            return response()->json(['message' => 'Unfollowed ticket.', 'following' => false]);
        }

        $ticket->follow($userId);

        return response()->json(['message' => 'Following ticket.', 'following' => true]);
    }

    public function applyMacro(Ticket $ticket, Request $request, MacroService $macroService): JsonResponse
    {
        $validated = $request->validate([
            'macro_id' => ['required', 'integer', Rule::exists(Escalated::table('macros'), 'id')],
        ]);

        $macro = Macro::forAgent($request->user()->getKey())->findOrFail($validated['macro_id']);
        $macroService->apply($macro, $ticket, $request->user());

        return response()->json(['message' => "Macro \"{$macro->name}\" applied."]);
    }

    public function tags(Ticket $ticket, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tag_ids' => 'required|array',
            'tag_ids.*' => ['integer', Rule::exists(Escalated::table('tags'), 'id')],
        ]);

        $newTagIds = collect($validated['tag_ids'])->map(fn ($id) => (int) $id);
        $currentTagIds = $ticket->tags()->pluck('id');

        $toAdd = $newTagIds->diff($currentTagIds)->values()->all();
        $toRemove = $currentTagIds->diff($newTagIds)->values()->all();

        if ($toAdd) {
            $this->ticketService->addTags($ticket, $toAdd, $request->user());
        }
        if ($toRemove) {
            $this->ticketService->removeTags($ticket, $toRemove, $request->user());
        }

        return response()->json(['message' => 'Tags updated.']);
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted.']);
    }
}

<?php

namespace Escalated\Laravel\Http\Controllers\Api;

use Escalated\Laravel\Http\Requests\CreateTicketRequest;
use Escalated\Laravel\Http\Requests\ReplyToTicketRequest;
use Escalated\Laravel\Http\Resources\MobileTicketResource;
use Escalated\Laravel\Models\SatisfactionRating;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MobileTicketController extends Controller
{
    public function __construct(protected TicketService $ticketService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $filters = $request->only(['status', 'priority', 'search', 'per_page']);
        $filters['per_page'] = min((int) ($filters['per_page'] ?? 15), 100);

        $tickets = $this->ticketService->list($filters, $request->user());

        return response()->json([
            'data' => $tickets->getCollection()->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'priority' => $ticket->priority->value,
                'priority_label' => $ticket->priority->label(),
                'requester' => [
                    'name' => $ticket->requester_name,
                    'email' => $ticket->requester_email,
                ],
                'assignee' => $ticket->assignee ? [
                    'id' => $ticket->assignee->getKey(),
                    'name' => $ticket->assignee->name,
                ] : null,
                'department' => $ticket->department ? [
                    'id' => $ticket->department->id,
                    'name' => $ticket->department->name,
                ] : null,
                'sla_breached' => $ticket->sla_first_response_breached || $ticket->sla_resolution_breached,
                'last_reply_at' => $ticket->last_reply_at,
                'created_at' => $ticket->created_at->toIso8601String(),
                'updated_at' => $ticket->updated_at->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function show(Ticket $ticket, Request $request): JsonResponse
    {
        $this->authorizeCustomer($ticket, $request);

        $ticket->load([
            'replies' => fn ($query) => $query->where('is_internal_note', false)->with('author', 'attachments')->latest(),
            'attachments',
            'tags',
            'department',
            'assignee',
            'satisfactionRating',
        ]);

        return response()->json([
            'data' => new MobileTicketResource($ticket),
        ]);
    }

    public function store(CreateTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->create($request->user(), [
            ...$request->validated(),
            'channel' => 'web',
            'attachments' => $request->file('attachments', []),
        ]);

        $ticket->load(['department', 'tags', 'assignee', 'attachments']);

        return response()->json([
            'data' => new MobileTicketResource($ticket),
            'message' => 'Ticket created.',
        ], 201);
    }

    public function reply(Ticket $ticket, ReplyToTicketRequest $request): JsonResponse
    {
        $this->authorizeCustomer($ticket, $request);

        $this->ticketService->reply(
            $ticket,
            $request->user(),
            $request->validated('body'),
            $request->file('attachments', [])
        );

        return response()->json([
            'message' => 'Reply sent.',
        ], 201);
    }

    public function close(Ticket $ticket, Request $request): JsonResponse
    {
        $this->authorizeCustomer($ticket, $request);

        if (! config('escalated.tickets.allow_customer_close')) {
            return response()->json(['message' => 'Customers cannot close this ticket.'], 403);
        }

        $this->ticketService->close($ticket, $request->user());

        return $this->show($ticket->fresh(), $request);
    }

    public function reopen(Ticket $ticket, Request $request): JsonResponse
    {
        $this->authorizeCustomer($ticket, $request);

        $this->ticketService->reopen($ticket, $request->user());

        return $this->show($ticket->fresh(), $request);
    }

    public function rate(Ticket $ticket, Request $request): JsonResponse
    {
        $this->authorizeCustomer($ticket, $request);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! in_array($ticket->status->value, ['resolved', 'closed'], true)) {
            return response()->json(['message' => 'You can only rate resolved or closed tickets.'], 422);
        }

        if ($ticket->satisfactionRating()->exists()) {
            return response()->json(['message' => 'This ticket has already been rated.'], 422);
        }

        SatisfactionRating::create([
            'ticket_id' => $ticket->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'rated_by_type' => $request->user()->getMorphClass(),
            'rated_by_id' => $request->user()->getKey(),
        ]);

        return response()->json(['message' => 'Thank you for your feedback.']);
    }

    protected function authorizeCustomer(Ticket $ticket, Request $request): void
    {
        if ($ticket->requester_type !== $request->user()->getMorphClass()
            || $ticket->requester_id !== $request->user()->getKey()) {
            abort(403);
        }
    }
}

<?php

namespace Escalated\Laravel\Http\Controllers\Customer;

use Escalated\Laravel\Http\Requests\CreateTicketRequest;
use Escalated\Laravel\Http\Requests\ReplyToTicketRequest;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(Request $request): mixed
    {
        $tickets = $this->ticketService->list(
            $request->only(['status', 'search', 'sort_by', 'sort_dir']),
            $request->user()
        );

        return $this->renderer->render('Escalated/Customer/Index', [
            'tickets' => $tickets,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function create(): mixed
    {
        return $this->renderer->render('Escalated/Customer/Create', [
            'departments' => Department::active()->get(['id', 'name']),
            'priorities' => config('escalated.priorities'),
        ]);
    }

    public function store(CreateTicketRequest $request): RedirectResponse
    {
        $ticket = $this->ticketService->create($request->user(), $request->validated());

        return redirect()
            ->route('escalated.customer.tickets.show', $ticket->reference)
            ->with('success', __('escalated::messages.ticket.created'));
    }

    public function show(Ticket $ticket, Request $request): mixed
    {
        $this->authorizeCustomer($ticket, $request);

        $ticket->load(['replies' => function ($q) {
            $q->where('is_internal_note', false)->with('author', 'attachments')->latest();
        }, 'attachments', 'tags', 'department']);

        return $this->renderer->render('Escalated/Customer/Show', [
            'ticket' => $ticket,
        ]);
    }

    public function reply(Ticket $ticket, ReplyToTicketRequest $request): RedirectResponse
    {
        $this->authorizeCustomer($ticket, $request);

        $this->ticketService->reply(
            $ticket,
            $request->user(),
            $request->validated('body'),
            $request->file('attachments', [])
        );

        return back()->with('success', __('escalated::messages.ticket.reply_sent'));
    }

    public function close(Ticket $ticket, Request $request): RedirectResponse
    {
        $this->authorizeCustomer($ticket, $request);

        if (! config('escalated.tickets.allow_customer_close')) {
            abort(403, __('escalated::messages.ticket.customers_cannot_close'));
        }

        $this->ticketService->close($ticket, $request->user());

        return back()->with('success', __('escalated::messages.ticket.closed'));
    }

    public function reopen(Ticket $ticket, Request $request): RedirectResponse
    {
        $this->authorizeCustomer($ticket, $request);

        $this->ticketService->reopen($ticket, $request->user());

        return back()->with('success', __('escalated::messages.ticket.reopened'));
    }

    protected function authorizeCustomer(Ticket $ticket, Request $request): void
    {
        if ($ticket->requester_type !== $request->user()->getMorphClass()
            || $ticket->requester_id !== $request->user()->getKey()) {
            abort(403);
        }
    }
}

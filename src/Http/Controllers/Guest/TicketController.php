<?php

namespace Escalated\Laravel\Http\Controllers\Guest;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;

class TicketController extends Controller
{
    public function __construct(
        protected AttachmentService $attachmentService,
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function create(): mixed
    {
        if (! EscalatedSettings::guestTicketsEnabled()) {
            abort(404);
        }

        return $this->renderer->render('Escalated/Guest/Create', [
            'departments' => Department::active()->get(['id', 'name']),
            'priorities' => config('escalated.priorities'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! EscalatedSettings::guestTicketsEnabled()) {
            abort(404);
        }

        $maxSize = config('escalated.tickets.max_attachment_size_kb', 10240);

        $validated = $request->validate([
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['nullable', 'in:low,medium,high,urgent,critical'],
            'department_id' => ['nullable', 'exists:'.config('escalated.table_prefix', 'escalated_').'departments,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:'.$maxSize],
        ]);

        $ticket = new Ticket();
        $ticket->reference = Ticket::generateReference();
        $ticket->requester_type = null;
        $ticket->requester_id = null;
        $ticket->guest_name = $validated['guest_name'];
        $ticket->guest_email = $validated['guest_email'];
        $ticket->guest_token = Str::random(64);
        $ticket->subject = $validated['subject'];
        $ticket->description = $validated['description'];
        $ticket->status = TicketStatus::Open;
        $ticket->priority = TicketPriority::tryFrom($validated['priority'] ?? '') ?? TicketPriority::from(config('escalated.default_priority', 'medium'));
        $ticket->channel = 'web';
        $ticket->department_id = $validated['department_id'] ?? null;
        $ticket->save();

        if (! empty($validated['attachments'])) {
            $this->attachmentService->storeMany($ticket, $request->file('attachments'));
        }

        Events\TicketCreated::dispatch($ticket);

        return redirect()
            ->route('escalated.guest.tickets.show', $ticket->guest_token)
            ->with('success', __('escalated::messages.guest.created'));
    }

    public function show(string $token): mixed
    {
        $ticket = Ticket::where('guest_token', $token)->firstOrFail();

        $ticket->load(['replies' => function ($q) {
            $q->where('is_internal_note', false)->with('author', 'attachments')->latest();
        }, 'attachments', 'department']);

        return $this->renderer->render('Escalated/Guest/Show', [
            'ticket' => $ticket,
            'token' => $token,
        ]);
    }

    public function reply(string $token, Request $request): RedirectResponse
    {
        $ticket = Ticket::where('guest_token', $token)->firstOrFail();

        if ($ticket->status === TicketStatus::Closed) {
            return back()->with('error', __('escalated::messages.guest.ticket_closed'));
        }

        $validated = $request->validate([
            'body' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:'.config('escalated.tickets.max_attachment_size_kb', 10240)],
        ]);

        $reply = new Reply();
        $reply->ticket_id = $ticket->id;
        $reply->author_type = null;
        $reply->author_id = null;
        $reply->body = $validated['body'];
        $reply->is_internal_note = false;
        $reply->type = 'reply';
        $reply->save();

        if (! empty($request->file('attachments'))) {
            $this->attachmentService->storeMany($reply, $request->file('attachments'));
        }

        Events\ReplyCreated::dispatch($reply);

        return back()->with('success', __('escalated::messages.ticket.reply_sent'));
    }
}

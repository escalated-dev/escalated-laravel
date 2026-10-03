<?php

namespace Escalated\Laravel\Http\Controllers\Guest;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Services\AttachmentAccess;
use Escalated\Laravel\Services\AttachmentService;
use Escalated\Laravel\Services\GuestAccess;
use Escalated\Laravel\Services\GuestTicketService;
use Escalated\Laravel\Support\CustomerTicketPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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
            'verification_url' => route('escalated.guest.verification'),
            'lookup_url' => route('escalated.guest.lookup'),
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
            'verification_id' => ['required', 'uuid'],
            'verification_code' => ['required', 'string', 'max:16'],
            'description' => ['required', 'string', 'max:65535'],
            'priority' => ['nullable', 'in:low,medium,high,urgent,critical'],
            'department_id' => ['nullable', 'exists:'.Department::class.',id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:'.$maxSize],
        ]);

        $result = app(GuestTicketService::class)->create(
            $validated + ['name' => $validated['guest_name'], 'email' => $validated['guest_email']],
            'web', $request->file('attachments', [])
        );
        $ticket = $result['ticket'];
        $token = $result['token'];

        return redirect()
            ->route('escalated.guest.tickets.show', $token)
            ->with('success', __('escalated::messages.guest.created'));
    }

    public function show(string $token): mixed
    {
        $ticket = app(GuestAccess::class)->resolve($token);
        app(AttachmentAccess::class)->forGuest($ticket);

        $ticket->load(['replies' => function ($q) {
            $q->where('is_internal_note', false)->with('author', 'attachments')->latest();
        }, 'attachments', 'department', 'satisfactionRating']);

        return $this->renderer->render('Escalated/Guest/Show', [
            'ticket' => CustomerTicketPayload::guestWeb($ticket),
            'token' => $token,
        ]);
    }

    public function reply(string $token, Request $request): RedirectResponse
    {
        $ticket = app(GuestAccess::class)->resolve($token);

        if ($ticket->status === TicketStatus::Closed) {
            return back()->with('error', __('escalated::messages.guest.ticket_closed'));
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:65535'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:'.config('escalated.tickets.max_attachment_size_kb', 10240)],
        ]);

        $reply = new Reply;
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

        // ReplyCreated event is automatically dispatched by Reply::booted()

        return back()->with('success', __('escalated::messages.ticket.reply_sent'));
    }
}

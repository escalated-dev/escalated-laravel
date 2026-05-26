<?php

namespace Escalated\Laravel\Http\Controllers\Api;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Http\Resources\MobileTicketResource;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class MobileGuestTicketController extends Controller
{
    public function __construct(protected AttachmentService $attachmentService) {}

    public function store(Request $request): JsonResponse
    {
        if (! EscalatedSettings::guestTicketsEnabled()) {
            return response()->json(['message' => 'Guest tickets are not enabled.'], 403);
        }

        $maxSize = config('escalated.tickets.max_attachment_size_kb', 10240);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['nullable', 'in:low,medium,high,urgent,critical'],
            'department_id' => ['nullable', 'exists:'.config('escalated.table_prefix', 'escalated_').'departments,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:'.$maxSize],
        ]);

        $contact = Contact::findOrCreateByEmail($validated['email'], $validated['name']);
        $token = Str::random(64);

        $ticket = Ticket::create([
            'guest_name' => $validated['name'],
            'guest_email' => $validated['email'],
            'guest_token' => $token,
            'contact_id' => $contact->id,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::tryFrom($validated['priority'] ?? '') ?? TicketPriority::from(config('escalated.default_priority', 'medium')),
            'channel' => 'web',
            'department_id' => $validated['department_id'] ?? null,
        ]);

        if ($request->hasFile('attachments')) {
            $this->attachmentService->storeMany($ticket, $request->file('attachments', []));
        }

        $ticket->load(['department', 'attachments']);

        return response()->json([
            'data' => new MobileTicketResource($ticket, $token),
            'message' => 'Ticket created.',
        ], 201);
    }

    public function show(string $token): JsonResponse
    {
        $ticket = Ticket::query()
            ->where('guest_token', $token)
            ->firstOrFail();

        $ticket->load([
            'replies' => fn ($query) => $query->where('is_internal_note', false)->with('author', 'attachments')->latest(),
            'attachments',
            'department',
            'satisfactionRating',
        ]);

        return response()->json([
            'data' => new MobileTicketResource($ticket, $token),
        ]);
    }

    public function reply(string $token, Request $request): JsonResponse
    {
        $ticket = Ticket::query()->where('guest_token', $token)->firstOrFail();

        if ($ticket->status === TicketStatus::Closed) {
            return response()->json(['message' => 'This ticket is closed.'], 422);
        }

        $validated = $request->validate([
            'body' => ['required', 'string'],
            'email' => ['required', 'email'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:'.config('escalated.tickets.max_attachment_size_kb', 10240)],
        ]);

        if (! hash_equals((string) $ticket->guest_email, (string) $validated['email'])) {
            return response()->json(['message' => 'The provided email does not match this ticket.'], 403);
        }

        $reply = new Reply;
        $reply->ticket_id = $ticket->id;
        $reply->author_type = null;
        $reply->author_id = null;
        $reply->body = $validated['body'];
        $reply->is_internal_note = false;
        $reply->type = 'reply';
        $reply->save();

        if ($request->hasFile('attachments')) {
            $this->attachmentService->storeMany($reply, $request->file('attachments', []));
        }

        return response()->json([
            'message' => 'Reply sent.',
        ], 201);
    }
}

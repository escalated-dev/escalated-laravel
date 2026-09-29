<?php

namespace Escalated\Laravel\Http\Controllers\Api;

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Http\Resources\MobileTicketResource;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Services\AttachmentAccess;
use Escalated\Laravel\Services\AttachmentService;
use Escalated\Laravel\Services\GuestAccess;
use Escalated\Laravel\Services\GuestEmailVerification;
use Escalated\Laravel\Services\GuestTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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
            'verification_id' => ['required', 'uuid'],
            'verification_code' => ['required', 'string', 'max:16'],
            'description' => ['required', 'string', 'max:65535'],
            'priority' => ['nullable', 'in:low,medium,high,urgent,critical'],
            'department_id' => ['nullable', 'exists:'.Department::class.',id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:'.$maxSize],
        ]);

        $result = app(GuestTicketService::class)->create(
            $validated,
            'web', $request->file('attachments', [])
        );
        $ticket = $result['ticket'];
        $token = $result['token'];

        $ticket->load(['department', 'attachments']);
        app(AttachmentAccess::class)->forGuest($ticket);

        return response()->json([
            'data' => new MobileTicketResource($ticket, $token),
            'message' => 'Ticket created.',
        ], 201);
    }

    public function show(string $token): JsonResponse
    {
        $ticket = app(GuestAccess::class)->resolve($token);

        app(AttachmentAccess::class)->forGuest($ticket);

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
        $ticket = app(GuestAccess::class)->resolve($token);

        if ($ticket->status === TicketStatus::Closed) {
            return response()->json(['message' => 'This ticket is closed.'], 422);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:65535'],
            'email' => ['required', 'email'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:'.config('escalated.tickets.max_attachment_size_kb', 10240)],
        ]);

        if (! hash_equals((string) $ticket->guest_email, GuestEmailVerification::email($validated['email']))) {
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

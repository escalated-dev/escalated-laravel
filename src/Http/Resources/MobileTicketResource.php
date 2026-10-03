<?php

namespace Escalated\Laravel\Http\Resources;

use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\CustomerTicketPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Requester-facing ticket payload for the mobile API (authenticated
 * customers and verified guests). Fields are allow-listed through
 * {@see CustomerTicketPayload}: staff appear by display name only, and
 * internal metadata is never included. `metadata` stays an empty JSON
 * object so existing clients keep decoding it.
 */
class MobileTicketResource extends JsonResource
{
    public function __construct($resource, protected ?string $guestAccessToken = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->resource;

        return [
            'id' => $ticket->id,
            'reference' => $ticket->reference,
            'guest_access_token' => $this->guestAccessToken,
            'guest_access_expires_at' => $this->guestAccessToken ? $ticket->guest_access_expires_at?->toIso8601String() : null,
            'subject' => $ticket->subject,
            'description' => $ticket->description ?? '',
            'status' => [
                'value' => $ticket->status->value,
                'label' => $ticket->status->label(),
            ],
            'priority' => [
                'value' => $ticket->priority->value,
                'label' => $ticket->priority->label(),
            ],
            'channel' => $ticket->channel->value,
            'metadata' => new \stdClass,
            'requester' => CustomerTicketPayload::requester($ticket),
            'assignee' => CustomerTicketPayload::assignee($ticket),
            'department' => $ticket->department ? [
                'id' => $ticket->department->id,
                'name' => $ticket->department->name,
            ] : null,
            'tags' => $ticket->relationLoaded('tags')
                ? $ticket->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ])->values()
                : [],
            'replies' => $ticket->relationLoaded('replies')
                ? $ticket->replies->reject(fn (Reply $reply) => (bool) $reply->is_internal_note)->map(fn (Reply $reply) => [
                    'id' => $reply->id,
                    'body' => $reply->body,
                    'is_internal_note' => false,
                    'is_pinned' => $reply->is_pinned ?? false,
                    'author' => CustomerTicketPayload::replyAuthor($ticket, $reply),
                    'attachments' => $reply->relationLoaded('attachments')
                        ? $reply->attachments->map(fn ($attachment) => [
                            'id' => $attachment->id,
                            'filename' => $attachment->filename,
                            'mime_type' => $attachment->mime_type,
                            'size' => $attachment->size,
                            'url' => $attachment->url,
                        ])->values()
                        : [],
                    'created_at' => $reply->created_at->toIso8601String(),
                ])->values()
                : [],
            'activities' => [],
            'sla' => [
                'first_response_due_at' => $ticket->first_response_due_at?->toIso8601String(),
                'first_response_at' => $ticket->first_response_at?->toIso8601String(),
                'first_response_breached' => (bool) $ticket->sla_first_response_breached,
                'resolution_due_at' => $ticket->resolution_due_at?->toIso8601String(),
                'resolution_breached' => (bool) $ticket->sla_resolution_breached,
            ],
            'is_following' => false,
            'followers_count' => 0,
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'created_at' => $ticket->created_at->toIso8601String(),
            'updated_at' => $ticket->updated_at->toIso8601String(),
        ];
    }
}

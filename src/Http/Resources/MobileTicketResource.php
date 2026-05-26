<?php

namespace Escalated\Laravel\Http\Resources;

use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'metadata' => $ticket->metadata ?? [],
            'requester' => [
                'name' => $ticket->requester_name,
                'email' => $ticket->requester_email,
            ],
            'assignee' => $ticket->assignee ? [
                'id' => $ticket->assignee->getKey(),
                'name' => $ticket->assignee->name,
                'email' => $ticket->assignee->email,
            ] : null,
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
                ? $ticket->replies->map(fn (Reply $reply) => [
                    'id' => $reply->id,
                    'body' => $reply->body,
                    'is_internal_note' => $reply->is_internal_note,
                    'is_pinned' => $reply->is_pinned ?? false,
                    'author' => [
                        'id' => $reply->author?->getKey() ?? 0,
                        'name' => $reply->author?->name ?? $ticket->guest_name ?? 'Guest',
                        'email' => $reply->author?->email ?? $ticket->guest_email ?? '',
                    ],
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

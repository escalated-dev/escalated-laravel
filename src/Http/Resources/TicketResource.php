<?php

namespace Escalated\Laravel\Http\Resources;

use Escalated\Laravel\Services\TicketActionRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'channel' => $this->channel,
            'metadata' => $this->metadata,
            'requester' => [
                'name' => $this->requester_name,
                'email' => $this->requester_email,
            ],
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->getKey(),
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ] : null),
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])),
            'replies' => $this->whenLoaded('replies', fn () => ReplyResource::collection($this->replies)),
            'activities' => $this->whenLoaded('activities', fn () => $this->activities->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'description' => $a->description,
                'causer' => $a->causer ? ['id' => $a->causer->getKey(), 'name' => $a->causer->name] : null,
                'created_at' => $a->created_at->toIso8601String(),
            ])),
            'contexts' => $this->whenLoaded('contexts', fn () => $this->contexts->map(fn ($context) => [
                'id' => $context->id,
                'type' => $context->attachable_type,
                'key' => $context->attachable_id,
                'label' => $context->label,
                'display' => $context->display,
                'metadata' => $context->metadata ?? [],
                'sort_order' => $context->sort_order,
            ])),
            'sla' => [
                'first_response_due_at' => $this->first_response_due_at?->toIso8601String(),
                'first_response_at' => $this->first_response_at?->toIso8601String(),
                'first_response_breached' => $this->sla_first_response_breached,
                'resolution_due_at' => $this->resolution_due_at?->toIso8601String(),
                'resolution_breached' => $this->sla_resolution_breached,
            ],
            'is_following' => $this->when(isset($this->is_following), $this->is_following ?? false),
            'followers_count' => $this->when(isset($this->followers_count), $this->followers_count ?? 0),
            'custom_actions' => $request->user()
                ? collect(app(TicketActionRegistry::class)->forTicket($this->resource, $request->user()))
                    ->map(fn (array $action) => array_merge($action, [
                        'url' => Route::has('escalated.api.tickets.custom-action')
                            ? route('escalated.api.tickets.custom-action', [$this->reference, $action['key']])
                            : null,
                        'method' => 'post',
                    ]))
                    ->values()
                    ->all()
                : [],
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

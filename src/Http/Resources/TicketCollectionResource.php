<?php

namespace Escalated\Laravel\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketCollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'ticket_type' => $this->ticket_type,
            'account' => $this->when(config('escalated.accounts.enabled', false), fn () => $this->account_type !== null ? [
                'type' => $this->account_type, 'id' => (string) $this->account_id,
            ] : null),
            'subject' => $this->subject,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'requester' => [
                'name' => $this->requester_name,
                'email' => $this->requester_email,
            ],
            'assignee' => $this->assignee ? [
                'id' => $this->assignee->getKey(),
                'name' => $this->assignee->name,
            ] : null,
            'department' => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null,
            'sla_breached' => $this->sla_first_response_breached || $this->sla_resolution_breached,
            'last_reply_at' => $this->last_reply_at,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

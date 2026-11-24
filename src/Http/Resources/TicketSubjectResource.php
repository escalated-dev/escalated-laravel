<?php

namespace Escalated\Laravel\Http\Resources;

use Escalated\Laravel\Contracts\TicketSubject;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\TicketSubjectResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class TicketSubjectResource extends JsonResource
{
    /**
     * A ticket's loaded subjects, serialised for the request's user. The API
     * ticket resource and the agent/admin ticket pages both use this, so the
     * sidebar's Related card gets the same shape everywhere.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forTicket(Ticket $ticket, Request $request): Collection
    {
        return $ticket->subjects->map(function ($link) use ($ticket, $request) {
            $link->setRelation('ticket', $ticket);

            return (new self($link))->toArray($request);
        })->values();
    }

    public function toArray(Request $request): array
    {
        $subject = $this->subject;
        if ($subject && ! app(TicketSubjectResolver::class)->canReference($subject, $request->user(), $this->ticket, 'view')) {
            $subject = null;
        }
        $presents = $subject instanceof TicketSubject;

        return [
            'link_id' => $this->id, 'type' => $this->subject_type, 'id' => $this->subject_id,
            'role' => $this->role, 'position' => $this->position,
            'title' => $presents ? $subject->ticketSubjectTitle()
                : (is_string($subject?->name ?? null) ? $subject->name : class_basename($this->subject_type).' #'.$this->subject_id),
            'subtitle' => $presents ? $subject->ticketSubjectSubtitle() : null,
            'url' => $presents ? $subject->ticketSubjectUrl() : null,
            'color' => $presents ? $subject->ticketSubjectColor() : null,
            'icon' => $presents ? $subject->ticketSubjectIcon() : null,
            'missing' => $subject === null,
        ];
    }
}

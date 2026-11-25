<?php

namespace Escalated\Laravel\Http\Resources;

use Escalated\Laravel\Contracts\TicketSubject;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketSubjectLink;
use Escalated\Laravel\Services\TicketSubjectResolver;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        return $ticket->subjects->map(function (TicketSubjectLink $link) use ($ticket, $request) {
            $link->setRelation('ticket', $ticket);

            try {
                return (new self($link))->toArray($request);
            } finally {
                // The link is still the ticket's model; do not leave a
                // ticket -> subjects -> ticket loop on it.
                $link->unsetRelation('ticket');
            }
        })->values();
    }

    /**
     * Load a ticket's subject links and, for each link whose type still
     * names a model class, its subject. A link whose class was renamed or
     * removed (or dropped from the morph map) gets a null subject, so it
     * renders as missing instead of failing the whole page.
     */
    public static function loadFor(Ticket $ticket): Ticket
    {
        $links = $ticket->relationLoaded('subjects') ? $ticket->subjects : $ticket->subjects()->get();

        $resolvable = $links->filter(function (TicketSubjectLink $link) {
            $class = Relation::getMorphedModel((string) $link->subject_type) ?? $link->subject_type;

            return is_string($class) && $class !== '' && class_exists($class);
        });

        $resolvable->load('subject');
        $links->each(function (TicketSubjectLink $link) {
            if (! $link->relationLoaded('subject')) {
                $link->setRelation('subject', null);
            }
        });

        return $ticket->setRelation('subjects', $links);
    }

    /**
     * For the agent and admin ticket pages: each subject link keeps being the
     * real model (host code such as ticket actions reads $link->subject), but
     * serialises in the API resource's shape when the page props are built.
     */
    public static function presentOn(Ticket $ticket, Request $request): Ticket
    {
        self::loadFor($ticket);

        $presented = self::forTicket($ticket, $request);
        $ticket->subjects->values()->each(
            fn (TicketSubjectLink $link, int $i) => $link->presentAs($presented[$i])
        );

        return $ticket;
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

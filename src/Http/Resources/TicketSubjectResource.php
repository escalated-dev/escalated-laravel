<?php

namespace Escalated\Laravel\Http\Resources;

use Escalated\Laravel\Contracts\TicketSubject;
use Escalated\Laravel\Services\TicketSubjectResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketSubjectResource extends JsonResource
{
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

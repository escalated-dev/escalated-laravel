<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class TicketSubjectService
{
    /** Validate every model before changing a set; retained links keep their IDs. */
    public function replace(Ticket $ticket, iterable $entries, ?Model $actor = null): void
    {
        app(TenantContext::class)->assertOwns($ticket);
        $subjects = [];
        $seen = [];
        foreach ($entries as $entry) {
            [$subject, $role] = is_array($entry) ? [$entry[0] ?? null, $entry[1] ?? null] : [$entry, null];
            if (! $subject instanceof Model || ($role !== null && (! is_string($role) || mb_strlen($role) > 255))) {
                throw new \InvalidArgumentException('Ticket subjects require a model and optional role of at most 255 characters.');
            }
            app(TicketSubjectResolver::class)->assertAllowedModel($subject);
            $key = $subject->getMorphClass().':'.(string) $subject->getKey();
            if (isset($seen[$key])) {
                throw new \InvalidArgumentException('Ticket subjects must be unique.');
            }
            $seen[$key] = true;
            $subjects[] = [$subject, $role];
        }

        $ticket->getConnection()->transaction(function () use ($ticket, $subjects, $actor) {
            $locked = Ticket::on($ticket->getConnectionName())->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
            $ids = [];
            foreach ($subjects as $position => [$subject, $role]) {
                if ($actor && ! app(TicketSubjectResolver::class)->canReference($subject, $actor, $locked, 'attach')) {
                    throw new AuthorizationException('This ticket subject is no longer available.');
                }
                $ids[] = $locked->attachSubject($subject, $role, $position)->getKey();
            }
            $locked->subjects()->whereNotIn('id', $ids)->delete();
        });
        $ticket->unsetRelation('subjects');
    }
}

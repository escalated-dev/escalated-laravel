<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\RuleExecution;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Fire-once bookkeeping for the escalation and automation sweeps.
 *
 * A rule fires once per ticket. When a ticket stops matching the rule its
 * record is removed, so the rule fires again if the ticket matches later.
 * A rule with `repeat_after_hours` also fires again once that many hours have
 * passed since it last fired, while the ticket still matches.
 */
class RuleFireLog
{
    /**
     * Re-arm the rule for tickets that no longer match, and return the
     * matching tickets the rule is due to act on.
     *
     * @param  Collection<int, Ticket>  $matching
     * @return Collection<int, Ticket>
     */
    public function due(string $ruleType, int|string $ruleId, ?int $repeatAfterHours, Collection $matching): Collection
    {
        $fired = RuleExecution::query()
            ->where('rule_type', $ruleType)
            ->where('rule_id', $ruleId)
            ->get(['id', 'ticket_id', 'fired_at'])
            ->keyBy(fn (RuleExecution $execution) => (string) $execution->ticket_id);

        $matchingIds = $matching->map(fn (Ticket $ticket) => (string) $ticket->getKey())->flip();

        $stale = $fired->reject(fn (RuleExecution $execution, string $ticketId) => $matchingIds->has($ticketId))
            ->pluck('id');
        foreach ($stale->chunk(500) as $ids) {
            RuleExecution::query()->whereIn('id', $ids->values()->all())->delete();
        }

        $repeatAfter = $repeatAfterHours !== null && $repeatAfterHours > 0
            ? now()->subHours($repeatAfterHours)
            : null;

        return $matching->filter(function (Ticket $ticket) use ($fired, $repeatAfter) {
            $execution = $fired->get((string) $ticket->getKey());

            if ($execution === null) {
                return true;
            }

            return $repeatAfter !== null && $execution->fired_at !== null && $execution->fired_at->lte($repeatAfter);
        })->values();
    }

    /**
     * Record that the rule acted on the ticket now.
     */
    public function record(string $ruleType, int|string $ruleId, Ticket $ticket): void
    {
        $keys = ['rule_type' => $ruleType, 'rule_id' => $ruleId, 'ticket_id' => $ticket->getKey()];

        try {
            RuleExecution::query()->updateOrCreate($keys, ['fired_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent sweep inserted the row first; refresh its time.
            RuleExecution::query()->where($keys)->update(['fired_at' => now()]);
        }
    }

    /**
     * Forget every record of a rule, e.g. when the rule is deleted.
     */
    public function forget(string $ruleType, int|string $ruleId): void
    {
        RuleExecution::query()->where('rule_type', $ruleType)->where('rule_id', $ruleId)->delete();
    }
}

<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Support\EventVocabulary;

/**
 * Rewrites workflows saved under a legacy trigger name (e.g. ticket.replied)
 * to the canonical name of ADR 2026-10-21-event-vocabulary, so the builder
 * shows them under an option it offers. Every tenant's rows are rewritten.
 *
 * Workflow::scopeForEvent() keeps matching legacy names, so a row written
 * after this migration by an older client still fires. There is no down():
 * which rows were legacy is not recorded, and the canonical names fire on
 * every version that reads this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = Escalated::table('workflows');

        foreach (EventVocabulary::LEGACY as $legacy => $canonical) {
            Escalated::db()->table($table)
                ->where('trigger_event', $legacy)
                ->update(['trigger_event' => $canonical]);
        }
    }

    public function down(): void
    {
        //
    }
};

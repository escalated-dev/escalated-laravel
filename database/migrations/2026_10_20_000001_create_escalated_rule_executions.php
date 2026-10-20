<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;

/**
 * Fire log for escalation rules and automations.
 *
 * One row per (rule, ticket) records when the rule last acted on the ticket.
 * The sweeps skip a ticket that already has a row, so a rule fires once per
 * ticket; the row is removed once the ticket stops matching (re-arming the
 * rule), and an optional `repeat_after_hours` on the rule lets it fire again
 * after that many hours while the ticket still matches.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Escalated::schema();
        $table = Escalated::table('rule_executions');
        $index = 'esc_rule_exec_'.substr(sha1($table), 0, 12);

        $schema->create($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->id();
            $tenant = $blueprint->string('tenant_id', 128)->default('');
            if (in_array(Escalated::db()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $tenant->charset('utf8mb4')->collation('utf8mb4_bin');
            } elseif (Escalated::db()->getDriverName() === 'pgsql') {
                $tenant->collation('C');
            }
            $blueprint->string('rule_type', 32);
            $blueprint->unsignedBigInteger('rule_id');
            $blueprint->unsignedBigInteger('ticket_id');
            $blueprint->timestamp('fired_at');
            $blueprint->timestamps();

            $blueprint->unique(['rule_type', 'rule_id', 'ticket_id'], $index.'_unique');
            $blueprint->index('ticket_id', $index.'_ticket');
            $blueprint->index('tenant_id', $index.'_tenant');
        });

        foreach (['escalation_rules', 'automations'] as $name) {
            $schema->table(Escalated::table($name), function (Blueprint $blueprint) {
                $blueprint->unsignedInteger('repeat_after_hours')->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Escalated::schema();

        foreach (['escalation_rules', 'automations'] as $name) {
            $schema->table(Escalated::table($name), function (Blueprint $blueprint) {
                $blueprint->dropColumn('repeat_after_hours');
            });
        }

        $schema->dropIfExists(Escalated::table('rule_executions'));
    }
};

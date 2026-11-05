<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;

/*
 * Per-agent unread tracking (ADR 2026-11-05-agent-panel-data). A ticket's
 * last_customer_activity_at moves on creation and on customer-side public
 * replies; ticket_reads holds when each agent last read each ticket (NULL:
 * marked unread). Existing tickets keep NULL activity, so nothing turns
 * unread on upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Escalated::schema();
        $tickets = Escalated::table('tickets');
        $table = Escalated::table('ticket_reads');
        $index = 'esc_ticket_reads_'.substr(sha1($table), 0, 12);

        $schema->table($tickets, function (Blueprint $blueprint) use ($index) {
            $blueprint->timestamp('last_customer_activity_at')->nullable();
            $blueprint->index('last_customer_activity_at', $index.'_activity');
        });

        $schema->create($table, function (Blueprint $blueprint) use ($index, $tickets) {
            $blueprint->id();
            $tenant = $blueprint->string('tenant_id', 128)->default('');
            if (in_array(Escalated::db()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $tenant->charset('utf8mb4')->collation('utf8mb4_bin');
            } elseif (Escalated::db()->getDriverName() === 'pgsql') {
                $tenant->collation('C');
            }
            // No DB-level FK to the host users table (see the macros migration).
            Escalated::userForeignColumn($blueprint, 'user_id');
            $blueprint->foreignId('ticket_id')->constrained($tickets)->cascadeOnDelete();
            $blueprint->timestamp('last_read_at')->nullable();
            $blueprint->timestamps();

            $blueprint->unique(['user_id', 'ticket_id'], $index.'_unique');
            $blueprint->index('ticket_id', $index.'_ticket');
            $blueprint->index('tenant_id', $index.'_tenant');
        });
    }

    public function down(): void
    {
        $schema = Escalated::schema();
        $index = 'esc_ticket_reads_'.substr(sha1(Escalated::table('ticket_reads')), 0, 12);

        $schema->dropIfExists(Escalated::table('ticket_reads'));
        $schema->table(Escalated::table('tickets'), function (Blueprint $blueprint) use ($index) {
            $blueprint->dropIndex($index.'_activity');
            $blueprint->dropColumn('last_customer_activity_at');
        });
    }
};

<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;

/*
 * When each customer was last seen by the customer portal or the widget
 * (ADR 2026-11-05-agent-panel-data). The subject is a ticket's requester
 * (polymorphic: the host user model) or, for guests, its contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = Escalated::table('customer_presence');
        $index = 'esc_presence_'.substr(sha1($table), 0, 12);

        Escalated::schema()->create($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->id();
            $tenant = $blueprint->string('tenant_id', 128)->default('');
            if (in_array(Escalated::db()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $tenant->charset('utf8mb4')->collation('utf8mb4_bin');
            } elseif (Escalated::db()->getDriverName() === 'pgsql') {
                $tenant->collation('C');
            }
            $blueprint->string('subject_type', 191);
            // A string so it holds integer, UUID and ULID keys alike.
            $blueprint->string('subject_id', 64);
            $blueprint->timestamp('last_seen_at');
            $blueprint->timestamps();

            $blueprint->unique(['tenant_id', 'subject_type', 'subject_id'], $index.'_unique');
        });
    }

    public function down(): void
    {
        Escalated::schema()->dropIfExists(Escalated::table('customer_presence'));
    }
};

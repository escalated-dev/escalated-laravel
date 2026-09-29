<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['slack_events', 'slack_threads'] as $name) {
            Escalated::schema()->create(Escalated::table($name), function (Blueprint $table) use ($name) {
                $table->id();
                $tenant = $table->string('tenant_id', 128)->default('');
                if (in_array(Escalated::db()->getDriverName(), ['mysql', 'mariadb'], true)) {
                    $tenant->collation('utf8mb4_bin');
                } elseif (Escalated::db()->getDriverName() === 'pgsql') {
                    $tenant->collation('C');
                }
                $table->index('tenant_id');
                $table->string('app_key', 64);
                $table->string('workspace_id', 64);
                $table->string('channel_id', 64);
                $table->string('thread_ts', 32);
                $table->char('thread_key', 64);
                $table->unsignedBigInteger('ticket_id')->nullable();
                if ($name === 'slack_events') {
                    $table->char('event_key', 64)->unique();
                    $table->char('message_key', 64)->unique();
                    $table->string('event_id', 128);
                    $table->char('payload_hash', 64);
                    $table->longText('payload');
                    $table->string('status', 20)->default('pending');
                    $table->unsignedInteger('attempts')->default(0);
                    $table->timestamp('available_at')->nullable();
                    $table->timestamp('processed_at')->nullable();
                    $table->string('last_error', 255)->nullable();
                    $table->unsignedBigInteger('reply_id')->nullable();
                    $table->index(['tenant_id', 'status', 'available_at'], 'esc_slack_due');
                    $table->index('thread_key');
                } else {
                    $table->unique('thread_key');
                }
                // Retain deduplication receipts after a ticket is purged. The
                // scoped processor treats a missing linked ticket as terminal.
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Escalated::schema()->dropIfExists(Escalated::table('slack_events'));
        Escalated::schema()->dropIfExists(Escalated::table('slack_threads'));
    }
};

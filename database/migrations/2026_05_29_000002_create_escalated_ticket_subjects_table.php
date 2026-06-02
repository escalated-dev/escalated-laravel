<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket subjects — the host-app entities a ticket is *about* (a Project,
 * Customer, Lead, asset, …), distinct from the requester (the person who
 * raised it) and the subject *line* (free text). A ticket can reference
 * several heterogeneous host models; each is presented in the UI via the
 * `Escalated\Laravel\Contracts\TicketSubject` contract.
 *
 * `subject_id` is a string so it can hold any host primary key type
 * (integer, UUID, ULID, or other string) — subjects are arbitrary host
 * models, not necessarily the configured user model.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'ticket_subjects', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->string('role')->nullable()->comment('Optional label for this attachment, e.g. "project" or "account"');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('ticket_id')
                ->references('id')
                ->on($prefix.'tickets')
                ->cascadeOnDelete();

            $table->unique(['ticket_id', 'subject_type', 'subject_id'], 'escalated_ticket_subject_unique');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::dropIfExists($prefix.'ticket_subjects');
    }
};

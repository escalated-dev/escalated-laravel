<?php

use Escalated\Laravel\Escalated;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Escalated::table('delayed_actions'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained(Escalated::table('workflows'))->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained(Escalated::table('tickets'))->cascadeOnDelete();
            $table->json('action');
            $table->json('remaining_actions');
            $table->timestamp('execute_at');
            $table->boolean('executed')->default(false);
            $table->boolean('cancelled')->default(false);
            $table->timestamps();

            $table->index(['execute_at', 'executed', 'cancelled']);
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Escalated::table('delayed_actions'));
    }
};

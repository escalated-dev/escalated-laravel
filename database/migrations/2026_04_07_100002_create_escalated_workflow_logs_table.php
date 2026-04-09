<?php

use Escalated\Laravel\Escalated;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Escalated::table('workflow_logs'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained(Escalated::table('workflows'))->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained(Escalated::table('tickets'))->cascadeOnDelete();
            $table->string('trigger_event');
            $table->boolean('conditions_matched');
            $table->json('actions_executed')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('workflow_id');
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Escalated::table('workflow_logs'));
    }
};

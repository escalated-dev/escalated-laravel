<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'chat_sessions', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->string('customer_session_id', 64)->unique();
            $table->unsignedBigInteger('agent_id')->nullable()->index();
            $table->string('status')->default('waiting')->index();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('customer_typing_at')->nullable();
            $table->timestamp('agent_typing_at')->nullable();
            $table->json('metadata')->nullable();
            $table->tinyInteger('rating')->unsigned()->nullable();
            $table->text('rating_comment')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')
                ->references('id')
                ->on($prefix.'tickets')
                ->cascadeOnDelete();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::dropIfExists($prefix.'chat_sessions');
    }
};

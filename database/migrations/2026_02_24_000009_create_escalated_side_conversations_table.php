<?php

use Escalated\Laravel\Escalated;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'side_conversations', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->string('subject');
            $table->string('channel')->default('internal'); // internal, email
            $table->string('status')->default('open'); // open, closed
            Escalated::userForeignColumn($table, 'created_by')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')
                ->references('id')
                ->on($prefix.'tickets')
                ->cascadeOnDelete();

            $table->index('ticket_id');
        });

        Schema::create($prefix.'side_conversation_replies', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('side_conversation_id');
            $table->text('body');
            Escalated::userForeignColumn($table, 'author_id')->nullable();
            $table->timestamps();

            $table->foreign('side_conversation_id')
                ->references('id')
                ->on($prefix.'side_conversations')
                ->cascadeOnDelete();

            $table->index('side_conversation_id');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::dropIfExists($prefix.'side_conversation_replies');
        Schema::dropIfExists($prefix.'side_conversations');
    }
};

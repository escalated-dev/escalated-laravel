<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'ticket_contexts', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->string('attachable_type');
            $table->string('attachable_id');
            $table->string('label')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('ticket_id')
                ->references('id')
                ->on($prefix.'tickets')
                ->cascadeOnDelete();

            $table->index(['attachable_type', 'attachable_id'], 'ticket_contexts_attachable_index');
            $table->unique(['ticket_id', 'attachable_type', 'attachable_id'], 'ticket_contexts_unique');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::dropIfExists($prefix.'ticket_contexts');
    }
};

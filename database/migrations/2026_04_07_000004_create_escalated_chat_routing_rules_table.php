<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'chat_routing_rules', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('routing_strategy')->default('round_robin');
            $table->string('offline_behavior')->default('ticket_fallback');
            $table->unsignedInteger('max_queue_size')->default(10);
            $table->unsignedInteger('max_concurrent_per_agent')->default(5);
            $table->unsignedInteger('auto_close_after_minutes')->default(30);
            $table->text('queue_message')->nullable();
            $table->text('offline_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('department_id')
                ->references('id')
                ->on($prefix.'departments')
                ->nullOnDelete();

            $table->index('department_id');
            $table->index('position');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::dropIfExists($prefix.'chat_routing_rules');
    }
};

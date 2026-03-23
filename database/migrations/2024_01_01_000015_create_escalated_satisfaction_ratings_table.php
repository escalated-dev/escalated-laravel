<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'satisfaction_ratings', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained($prefix.'tickets')->cascadeOnDelete();
            $table->tinyInteger('rating');
            $table->text('comment')->nullable();
            $table->nullableMorphs('rated_by');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'satisfaction_ratings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'ticket_followers', function (Blueprint $table) use ($prefix) {
            $table->foreignId('ticket_id')->constrained($prefix.'tickets')->cascadeOnDelete();
            // No DB-level FK to host `users` — see #88 / macros migration for rationale.
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->unique(['ticket_id', 'user_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'ticket_followers');
    }
};

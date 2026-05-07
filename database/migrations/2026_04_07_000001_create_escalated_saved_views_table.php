<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'saved_views', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('filters');
            // No DB-level FK to host `users` — see #88 / macros migration for rationale.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->boolean('is_default')->default(false);
            $table->integer('position')->default(0);
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_shared']);
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'saved_views');
    }
};

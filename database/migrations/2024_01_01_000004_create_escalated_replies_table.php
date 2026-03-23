<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'replies', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('ticket_id')->constrained($prefix.'tickets')->cascadeOnDelete();
            $table->nullableMorphs('author');
            $table->text('body');
            $table->boolean('is_internal_note')->default(false);
            $table->string('type')->default('reply');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'replies');
    }
};

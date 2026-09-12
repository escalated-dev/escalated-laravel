<?php

use Escalated\Laravel\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color')->default('#6B7280');
            $table->timestamps();
        });

        Schema::create($prefix.'ticket_tag', function (Blueprint $table) use ($prefix) {
            $table->foreignId('ticket_id')->constrained($prefix.'tickets')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained($prefix.'tags')->cascadeOnDelete();
            $table->primary(['ticket_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'ticket_tag');
        Schema::dropIfExists($prefix.'tags');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'skill_routing_tags', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('skill_id');
            $table->unsignedBigInteger('tag_id');

            $table->foreign('skill_id')
                ->references('id')
                ->on($prefix.'skills')
                ->cascadeOnDelete();

            $table->foreign('tag_id')
                ->references('id')
                ->on($prefix.'tags')
                ->cascadeOnDelete();

            $table->unique(['skill_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'skill_routing_tags');
    }
};

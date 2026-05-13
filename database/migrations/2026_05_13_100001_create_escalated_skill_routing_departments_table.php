<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'skill_routing_departments', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('skill_id');
            $table->unsignedBigInteger('department_id');

            $table->foreign('skill_id')
                ->references('id')
                ->on($prefix.'skills')
                ->cascadeOnDelete();

            $table->foreign('department_id')
                ->references('id')
                ->on($prefix.'departments')
                ->cascadeOnDelete();

            $table->unique(['skill_id', 'department_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'skill_routing_departments');
    }
};

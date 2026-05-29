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

        Schema::create($prefix.'skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('routing_tag_ids')->nullable();
            $table->json('routing_department_ids')->nullable();
            $table->timestamps();
        });

        Schema::create($prefix.'agent_skill', function (Blueprint $table) use ($prefix) {
            $table->id();
            Escalated::userForeignColumn($table, 'user_id');
            $table->unsignedBigInteger('skill_id');
            $table->unsignedInteger('proficiency')->default(1); // 1-5

            $table->foreign('skill_id')
                ->references('id')
                ->on($prefix.'skills')
                ->cascadeOnDelete();

            $table->unique(['user_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::dropIfExists($prefix.'agent_skill');
        Schema::dropIfExists($prefix.'skills');
    }
};

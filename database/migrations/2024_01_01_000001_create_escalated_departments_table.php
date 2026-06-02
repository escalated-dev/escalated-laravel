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

        Schema::create($prefix.'departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create($prefix.'department_agent', function (Blueprint $table) use ($prefix) {
            $table->foreignId('department_id')->constrained($prefix.'departments')->cascadeOnDelete();
            Escalated::userForeignColumn($table, 'agent_id');
            $table->primary(['department_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'department_agent');
        Schema::dropIfExists($prefix.'departments');
    }
};

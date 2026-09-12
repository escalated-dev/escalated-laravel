<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'agent_profiles', function (Blueprint $table) {
            $table->id();
            Escalated::userForeignColumn($table, 'user_id')->unique();
            $table->string('agent_type')->default('full'); // full, light
            $table->unsignedInteger('max_tickets')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::dropIfExists($prefix.'agent_profiles');
    }
};

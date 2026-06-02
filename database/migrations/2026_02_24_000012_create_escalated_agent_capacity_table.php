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

        Schema::create($prefix.'agent_capacity', function (Blueprint $table) {
            $table->id();
            Escalated::userForeignColumn($table, 'user_id');
            $table->string('channel')->default('default');
            $table->unsignedInteger('max_concurrent')->default(10);
            $table->unsignedInteger('current_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'channel']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::dropIfExists($prefix.'agent_capacity');
    }
};

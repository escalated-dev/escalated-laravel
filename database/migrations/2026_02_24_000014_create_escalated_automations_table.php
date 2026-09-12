<?php

use Escalated\Laravel\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'automations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('conditions');
            $table->json('actions');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index('active');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::dropIfExists($prefix.'automations');
    }
};

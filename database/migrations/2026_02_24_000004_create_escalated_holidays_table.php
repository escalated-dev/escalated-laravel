<?php

use Escalated\Laravel\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'holidays', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('schedule_id')->constrained($prefix.'business_schedules')->cascadeOnDelete();
            $table->string('name');
            $table->date('date');
            $table->boolean('recurring')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'holidays');
    }
};

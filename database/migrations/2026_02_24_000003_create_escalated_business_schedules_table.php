<?php

use Escalated\Laravel\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'business_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('timezone')->default('UTC');
            $table->boolean('is_default')->default(false);
            $table->json('schedule'); // { monday: { start: "09:00", end: "17:00" }, ... }
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'business_schedules');
    }
};

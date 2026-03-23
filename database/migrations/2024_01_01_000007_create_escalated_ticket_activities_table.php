<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'ticket_activities', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('ticket_id')->constrained($prefix.'tickets')->cascadeOnDelete();
            $table->nullableMorphs('causer');
            $table->string('type');
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'ticket_activities');
    }
};

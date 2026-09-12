<?php

use Escalated\Laravel\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type'); // text, textarea, select, multi_select, checkbox, date, number
            $table->string('context')->default('ticket'); // ticket, user, organization
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->string('placeholder')->nullable();
            $table->text('description')->nullable();
            $table->json('validation_rules')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create($prefix.'custom_field_values', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('custom_field_id')->constrained($prefix.'custom_fields')->cascadeOnDelete();
            $table->morphs('entity'); // entity_type + entity_id
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'custom_field_values');
        Schema::dropIfExists($prefix.'custom_fields');
    }
};

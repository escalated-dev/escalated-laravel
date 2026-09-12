<?php

use Escalated\Laravel\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'custom_objects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('fields_schema');
            $table->timestamps();
        });

        Schema::create($prefix.'custom_object_records', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('object_id');
            $table->json('data');
            $table->timestamps();

            $table->foreign('object_id')
                ->references('id')
                ->on($prefix.'custom_objects')
                ->cascadeOnDelete();

            $table->index('object_id');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::dropIfExists($prefix.'custom_object_records');
        Schema::dropIfExists($prefix.'custom_objects');
    }
};

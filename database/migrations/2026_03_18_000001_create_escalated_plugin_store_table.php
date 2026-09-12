<?php

use Escalated\Laravel\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'plugin_store', function (Blueprint $table) {
            $table->id();
            $table->string('plugin', 50);
            $table->string('collection', 50);
            $table->string('key', 255)->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['plugin', 'collection', 'key']);
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'plugin_store');
    }
};

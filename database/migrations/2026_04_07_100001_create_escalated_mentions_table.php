<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'mentions', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('reply_id')->constrained($prefix.'replies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->datetime('read_at')->nullable();
            $table->timestamps();

            $table->unique(['reply_id', 'user_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'mentions');
    }
};

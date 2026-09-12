<?php

use Escalated\Laravel\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'ticket_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('slug')->unique();
            $table->string('category'); // new, open, pending, on_hold, solved
            $table->string('color')->default('#6b7280');
            $table->text('description')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'ticket_statuses');
    }
};

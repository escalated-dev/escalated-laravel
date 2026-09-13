<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Escalated::table('newsletter_lists'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('kind', ['static', 'dynamic']);
            $table->json('filter_json')->nullable();
            Escalated::userForeignColumn($table, 'created_by')->nullable();
            $table->timestamps();

            $table->index('kind');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Escalated::table('newsletter_lists'));
    }
};

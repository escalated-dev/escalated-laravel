<?php

use Escalated\Laravel\Escalated;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalated_newsletter_lists', function (Blueprint $table) {
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
        Schema::dropIfExists('escalated_newsletter_lists');
    }
};

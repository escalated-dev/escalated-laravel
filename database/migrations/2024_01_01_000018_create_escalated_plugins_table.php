<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalated_plugins', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->index('slug');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalated_plugins');
    }
};

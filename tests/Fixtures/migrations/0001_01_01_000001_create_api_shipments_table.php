<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Host subject fixtures must exist before RefreshDatabase begins its
        // transaction: MySQL DDL otherwise commits it and destroys savepoints.
        Schema::create('api_shipments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('account')->default('a');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_shipments');
    }
};

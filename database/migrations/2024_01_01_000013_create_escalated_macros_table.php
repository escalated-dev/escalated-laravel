<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'macros', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('actions');
            // No DB-level FK to host `users`. Host apps run on a wide range of
            // MySQL/MariaDB configurations (MyISAM `users` table, signed bigint
            // id from pre-Laravel-5.8 scaffolds, INT UNSIGNED from
            // `increments()`, UUID/CHAR(36) from `HasUuids`) — any of which
            // make MariaDB reject FK creation with errno 150. See #88.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_shared')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'macros');
    }
};

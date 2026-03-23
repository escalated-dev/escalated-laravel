<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'replies', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->after('is_internal_note');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'replies', function (Blueprint $table) {
            $table->dropColumn('is_pinned');
        });
    }
};

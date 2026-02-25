<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'tickets', function (Blueprint $table) use ($prefix) {
            $table->unsignedBigInteger('merged_into_id')->nullable()->after('department_id');
            $table->foreign('merged_into_id')
                ->references('id')
                ->on($prefix.'tickets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'tickets', function (Blueprint $table) {
            $table->dropForeign(['merged_into_id']);
            $table->dropColumn('merged_into_id');
        });
    }
};

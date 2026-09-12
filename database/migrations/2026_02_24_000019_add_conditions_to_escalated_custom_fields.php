<?php

use Escalated\Laravel\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'custom_fields', function (Blueprint $table) {
            $table->json('conditions')->nullable()->after('validation_rules');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'custom_fields', function (Blueprint $table) {
            $table->dropColumn('conditions');
        });
    }
};

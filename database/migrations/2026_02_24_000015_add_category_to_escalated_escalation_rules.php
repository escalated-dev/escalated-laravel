<?php

use Escalated\Laravel\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'escalation_rules', function (Blueprint $table) {
            $table->string('category')->default('Uncategorized')->after('name');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'escalation_rules', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};

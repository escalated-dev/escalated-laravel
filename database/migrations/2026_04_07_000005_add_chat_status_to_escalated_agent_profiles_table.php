<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'agent_profiles', function (Blueprint $table) {
            $table->string('chat_status')->default('offline')->after('max_tickets');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'agent_profiles', function (Blueprint $table) {
            $table->dropColumn('chat_status');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'tickets', function (Blueprint $table) {
            $table->string('ticket_type')->default('question')->after('priority')->index();
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'tickets', function (Blueprint $table) use ($prefix) {
            $table->dropIndex([$prefix.'tickets_ticket_type_index']);
            $table->dropColumn('ticket_type');
        });
    }
};

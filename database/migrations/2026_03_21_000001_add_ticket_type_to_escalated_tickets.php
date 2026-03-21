<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('escalated.table_prefix', 'escalated_').'tickets', function (Blueprint $table) {
            $table->string('ticket_type')->default('question')->after('priority')->index();
        });
    }

    public function down(): void
    {
        Schema::table(config('escalated.table_prefix', 'escalated_').'tickets', function (Blueprint $table) {
            $table->dropIndex([config('escalated.table_prefix', 'escalated_').'tickets_ticket_type_index']);
            $table->dropColumn('ticket_type');
        });
    }
};

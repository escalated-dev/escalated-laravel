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
            $table->dateTime('snoozed_until')->nullable()->after('closed_at');
            $table->unsignedBigInteger('snoozed_by')->nullable()->after('snoozed_until');
            $table->string('status_before_snooze')->nullable()->after('snoozed_by');

            $table->foreign('snoozed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('snoozed_until');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'tickets', function (Blueprint $table) {
            $table->dropForeign(['snoozed_by']);
            $table->dropIndex(['snoozed_until']);
            $table->dropColumn(['snoozed_until', 'snoozed_by', 'status_before_snooze']);
        });
    }
};

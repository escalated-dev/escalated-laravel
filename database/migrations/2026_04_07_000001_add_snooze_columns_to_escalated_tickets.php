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
            // No DB-level FK to host `users` — see #88 / macros migration for rationale.
            $table->unsignedBigInteger('snoozed_by')->nullable()->after('snoozed_until');
            $table->string('status_before_snooze')->nullable()->after('snoozed_by');

            $table->index('snoozed_until');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'tickets', function (Blueprint $table) {
            $table->dropIndex(['snoozed_until']);
            $table->dropColumn(['snoozed_until', 'snoozed_by', 'status_before_snooze']);
        });
    }
};

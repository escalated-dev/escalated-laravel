<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'tickets', function (Blueprint $table) {
            // Make requester nullable for guest tickets
            $table->string('requester_type')->nullable()->change();
            Escalated::userForeignColumn($table, 'requester_id')->nullable()->change();

            // Guest ticket fields
            $table->string('guest_name')->nullable()->after('requester_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_token', 64)->nullable()->unique()->after('guest_email');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        // A guest ticket is a row the pre-guest schema cannot hold: it has no
        // requester at all. Restoring NOT NULL over it fails outright on
        // PostgreSQL and MySQL -- SQLite rebuilds the table and lets the nulls
        // through, which is how this reverted cleanly in a SQLite-only suite
        // and would have failed on a real host.
        DB::connection($this->getConnection())
            ->table($prefix.'tickets')
            ->whereNull('requester_type')
            ->orWhereNull('requester_id')
            ->delete();

        Schema::table($prefix.'tickets', function (Blueprint $table) use ($prefix) {
            // The unique index has to go first. SQLite refuses to drop a column
            // an index still names, and leaving a stale index behind on the
            // other drivers is no better.
            $table->dropUnique($prefix.'tickets_guest_token_unique');
            $table->dropColumn(['guest_name', 'guest_email', 'guest_token']);
            $table->string('requester_type')->nullable(false)->change();
            Escalated::userForeignColumn($table, 'requester_id')->nullable(false)->change();
        });
    }
};

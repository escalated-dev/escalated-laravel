<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;

/*
 * The business schedule an SLA policy or department counts business hours
 * in. Null on every existing row, so deadlines are counted as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['sla_policies', 'departments'] as $table) {
            Escalated::schema()->table(Escalated::table($table), function (Blueprint $table) {
                $table->foreignId('business_schedule_id')->nullable()
                    ->constrained(Escalated::table('business_schedules'))->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['sla_policies', 'departments'] as $table) {
            Escalated::schema()->table(Escalated::table($table), function (Blueprint $table) {
                $table->dropConstrainedForeignId('business_schedule_id');
            });
        }
    }
};

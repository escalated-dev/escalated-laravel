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
            // Performance indexes for SLA checks, reports, and filtering
            $table->index('resolved_at');
            $table->index('first_response_at');
            $table->index('first_response_due_at');
            $table->index('resolution_due_at');
            $table->index('created_at');

            // Compound indexes for SLA breach checks
            $table->index(['sla_first_response_breached', 'first_response_due_at'], $prefix.'tickets_sla_fr_breach_idx');
            $table->index(['sla_resolution_breached', 'resolution_due_at'], $prefix.'tickets_sla_res_breach_idx');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'tickets', function (Blueprint $table) use ($prefix) {
            $table->dropIndex(['resolved_at']);
            $table->dropIndex(['first_response_at']);
            $table->dropIndex(['first_response_due_at']);
            $table->dropIndex(['resolution_due_at']);
            $table->dropIndex(['created_at']);
            $table->dropIndex($prefix.'tickets_sla_fr_breach_idx');
            $table->dropIndex($prefix.'tickets_sla_res_breach_idx');
        });
    }
};

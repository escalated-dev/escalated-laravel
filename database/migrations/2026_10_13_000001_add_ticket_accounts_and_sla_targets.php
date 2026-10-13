<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Escalated::schema()->table(Escalated::table('tickets'), function (Blueprint $table) {
            foreach (['account_type', 'account_id'] as $column) {
                $definition = $table->string($column)->nullable();
                if (in_array(Escalated::db()->getDriverName(), ['mysql', 'mariadb'], true)) {
                    $definition->collation('utf8mb4_bin');
                } elseif (Escalated::db()->getDriverName() === 'pgsql') {
                    $definition->collation('C');
                }
            }
            $table->index(['account_type', 'account_id'], 'esc_tickets_account');
        });
        Escalated::schema()->table(Escalated::table('sla_policies'), function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained(Escalated::table('departments'))->restrictOnDelete();
            $table->string('ticket_type')->nullable()->index();
        });
    }

    public function down(): void
    {
        Escalated::schema()->table(Escalated::table('sla_policies'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('ticket_type');
        });
        Escalated::schema()->table(Escalated::table('tickets'), function (Blueprint $table) {
            $table->dropIndex('esc_tickets_account');
            $table->dropColumn(['account_type', 'account_id']);
        });
    }
};

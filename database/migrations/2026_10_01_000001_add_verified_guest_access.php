<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Escalated::schema()->create(Escalated::table('guest_verifications'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $tenant = $table->string('tenant_id', 128)->default('');
            if (in_array(Escalated::db()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $tenant->collation('utf8mb4_bin');
            } elseif (Escalated::db()->getDriverName() === 'pgsql') {
                $tenant->collation('C');
            }
            $table->index('tenant_id');
            $table->text('email');
            $table->string('purpose', 20);
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
        Escalated::schema()->table(Escalated::table('tickets'), function (Blueprint $table) {
            $table->string('guest_access_hash', 64)->nullable();
            $table->timestamp('guest_access_expires_at')->nullable();
            $table->timestamp('guest_email_verified_at')->nullable();
            $table->string('guest_verified_email')->nullable();
            $table->string('external_reference')->nullable();
            $table->index(['tenant_id', 'external_reference'], 'esc_ticket_external_reference');
        });
    }

    public function down(): void
    {
        Escalated::schema()->dropIfExists(Escalated::table('guest_verifications'));
        Escalated::schema()->table(Escalated::table('tickets'), function (Blueprint $table) {
            $table->dropIndex('esc_ticket_external_reference');
            $table->dropColumn(['guest_access_hash', 'guest_access_expires_at', 'guest_email_verified_at', 'guest_verified_email', 'external_reference']);
        });
    }
};

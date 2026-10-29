<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;

/*
 * Single-use SSO message IDs: AuthnRequest IDs Escalated issued, and SAML
 * assertion IDs and JWT jtis it accepted (replay protection). expires_at is
 * Unix seconds; expired rows are pruned on write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Escalated::schema()->create(Escalated::table('sso_message_ids'), function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32);
            $table->string('message_id', 191);
            $table->unsignedBigInteger('expires_at');

            $table->unique(['kind', 'message_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Escalated::schema()->dropIfExists(Escalated::table('sso_message_ids'));
    }
};

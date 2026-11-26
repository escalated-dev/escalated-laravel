<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;

/*
 * One-time authorization codes for the browser extension sign-in (staff API
 * contract v1). Only the sha256 of a code is stored. Used rows are kept until
 * they expire so a replayed code can revoke the token it issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = Escalated::table('api_token_codes');
        $index = 'esc_api_token_codes_'.substr(sha1($table), 0, 12);

        Escalated::schema()->create($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->id();
            $tenant = $blueprint->string('tenant_id', 128)->default('');
            if (in_array(Escalated::db()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $tenant->charset('utf8mb4')->collation('utf8mb4_bin');
            } elseif (Escalated::db()->getDriverName() === 'pgsql') {
                $tenant->collation('C');
            }
            $blueprint->string('code', 64);
            Escalated::userMorphs($blueprint, 'tokenable');
            $blueprint->string('code_challenge', 128);
            $blueprint->string('redirect_uri', 255);
            $blueprint->string('client_name', 64)->nullable();
            $blueprint->string('extension_id', 32)->nullable();
            $blueprint->timestamp('expires_at');
            $blueprint->timestamp('used_at')->nullable();
            // No DB-level FK: the token row may be deleted (revoked) while the
            // used code is kept as a replay marker.
            $blueprint->unsignedBigInteger('api_token_id')->nullable();
            $blueprint->timestamps();

            $blueprint->unique('code', $index.'_code');
            $blueprint->index('tenant_id', $index.'_tenant');
        });
    }

    public function down(): void
    {
        Escalated::schema()->dropIfExists(Escalated::table('api_token_codes'));
    }
};

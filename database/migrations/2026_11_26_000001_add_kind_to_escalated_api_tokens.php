<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;

/*
 * Staff API contract v1: a token's kind ('browser_extension' for tokens minted
 * by the extension sign-in exchange, NULL for everything else) and the
 * extension ID it was issued to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Escalated::schema()->table(Escalated::table('api_tokens'), function (Blueprint $blueprint) {
            $blueprint->string('kind', 32)->nullable();
            $blueprint->string('extension_id', 32)->nullable();
        });
    }

    public function down(): void
    {
        Escalated::schema()->table(Escalated::table('api_tokens'), function (Blueprint $blueprint) {
            $blueprint->dropColumn(['kind', 'extension_id']);
        });
    }
};

<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;

/*
 * The TOTP time step of the last code accepted for a user, so the same code
 * (or an older one) cannot be used again inside its validity window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Escalated::schema()->table(Escalated::table('two_factor'), function (Blueprint $table) {
            $table->unsignedBigInteger('last_used_step')->nullable();
        });
    }

    public function down(): void
    {
        Escalated::schema()->table(Escalated::table('two_factor'), function (Blueprint $table) {
            $table->dropColumn('last_used_step');
        });
    }
};

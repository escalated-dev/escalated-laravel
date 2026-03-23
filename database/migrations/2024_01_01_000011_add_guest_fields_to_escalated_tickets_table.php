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
            // Make requester nullable for guest tickets
            $table->string('requester_type')->nullable()->change();
            $table->unsignedBigInteger('requester_id')->nullable()->change();

            // Guest ticket fields
            $table->string('guest_name')->nullable()->after('requester_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_token', 64)->nullable()->unique()->after('guest_email');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'tickets', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_email', 'guest_token']);
            $table->string('requester_type')->nullable(false)->change();
            $table->unsignedBigInteger('requester_id')->nullable(false)->change();
        });
    }
};

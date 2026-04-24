<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a first-class Contact entity for guest requesters, mirroring the
 * escalated-nestjs Contact design (Pattern B). Enables email-level dedupe
 * across tickets and a clean "promote to user" flow.
 *
 * Keeps the inline guest_name/guest_email/guest_token columns on tickets
 * for backwards compatibility (Pattern A). A follow-up backfill migration
 * populates contact_id for existing tickets; the dual-read period lets
 * callers transition without a flag day.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 320);
            $table->string('name')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()
                ->comment('Linked host-app user id once the contact creates an account');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('email');
            $table->index('user_id');
        });

        Schema::table($prefix.'tickets', function (Blueprint $table) use ($prefix) {
            $table->unsignedBigInteger('contact_id')->nullable()->after('guest_token');
            $table->foreign('contact_id')
                ->references('id')
                ->on($prefix.'contacts')
                ->nullOnDelete();
            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'tickets', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropIndex(['contact_id']);
            $table->dropColumn('contact_id');
        });

        Schema::dropIfExists($prefix.'contacts');
    }
};

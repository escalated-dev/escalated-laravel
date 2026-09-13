<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Escalated::table('newsletter_list_members'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('list_id');
            $table->unsignedBigInteger('contact_id');
            $table->timestamp('added_at')->useCurrent();
            Escalated::userForeignColumn($table, 'added_by')->nullable();

            $table->unique(['list_id', 'contact_id']);
            $table->index('contact_id');

            $table->foreign('list_id')->references('id')->on(Escalated::table('newsletter_lists'))->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on(Escalated::table('contacts'))->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Escalated::table('newsletter_list_members'));
    }
};

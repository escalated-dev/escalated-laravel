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
            $table->timestamp('chat_ended_at')->nullable()->after('closed_at');
            $table->json('chat_metadata')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::table($prefix.'tickets', function (Blueprint $table) {
            $table->dropColumn(['chat_ended_at', 'chat_metadata']);
        });
    }
};

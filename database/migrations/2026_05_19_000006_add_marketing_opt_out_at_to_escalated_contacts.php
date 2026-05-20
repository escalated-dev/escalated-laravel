<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escalated_contacts', function (Blueprint $table) {
            $table->timestamp('marketing_opt_out_at')->nullable()->after('metadata');
            $table->index('marketing_opt_out_at');
        });
    }

    public function down(): void
    {
        Schema::table('escalated_contacts', function (Blueprint $table) {
            $table->dropIndex(['marketing_opt_out_at']);
            $table->dropColumn('marketing_opt_out_at');
        });
    }
};

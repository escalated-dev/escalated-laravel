<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        $now = now();

        DB::table($prefix.'settings')->insert([
            ['key' => 'email_logo_url', 'value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'email_accent_color', 'value' => '#2d3748', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'email_footer_text', 'value' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        DB::table($prefix.'settings')
            ->whereIn('key', ['email_logo_url', 'email_accent_color', 'email_footer_text'])
            ->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default settings
        $now = now();

        DB::table($prefix.'settings')->insert([
            ['key' => 'guest_tickets_enabled', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'allow_customer_close', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'auto_close_resolved_after_days', 'value' => '7', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'max_attachments_per_reply', 'value' => '5', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'max_attachment_size_kb', 'value' => '10240', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'settings');
    }
};

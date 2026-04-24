<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backfills contact_id on existing tickets that have a guest_email.
 *   - Finds each distinct (lowercased, trimmed) guest_email
 *   - Upserts a Contact row
 *   - Sets ticket.contact_id on all tickets matching that email
 *
 * Safe to re-run: the upsert is idempotent on the unique email index.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        $tickets = $prefix.'tickets';
        $contacts = $prefix.'contacts';

        $rows = DB::table($tickets)
            ->whereNotNull('guest_email')
            ->whereNull('contact_id')
            ->select('id', 'guest_email', 'guest_name')
            ->get();

        $seen = []; // normalized email => contact id
        foreach ($rows as $row) {
            $email = Str::lower(trim($row->guest_email));
            if ($email === '') {
                continue;
            }

            if (! isset($seen[$email])) {
                $existing = DB::table($contacts)->where('email', $email)->first();
                if ($existing) {
                    $seen[$email] = $existing->id;
                } else {
                    $seen[$email] = DB::table($contacts)->insertGetId([
                        'email' => $email,
                        'name' => $row->guest_name ?: null,
                        'user_id' => null,
                        'metadata' => json_encode([]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table($tickets)
                ->where('id', $row->id)
                ->update(['contact_id' => $seen[$email]]);
        }
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        // Reverse is simple: clear contact_id. The contacts rows
        // remain (a separate migration drops the table).
        DB::table($prefix.'tickets')->update(['contact_id' => null]);
    }
};

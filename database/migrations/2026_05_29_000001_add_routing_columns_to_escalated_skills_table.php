<?php

use Escalated\Laravel\Escalated;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills the skills routing columns for installs created before they were
 * added. The columns were introduced by editing the original
 * `create_escalated_skills_table` migration, which never re-runs on an existing
 * install — so apps that migrated an earlier version are missing them, and
 * `Skill::saving()` (which always writes them) would fail. This guarded ALTER
 * adds them only where absent, so it is a no-op on fresh installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = Escalated::table('skills');

        if (! Schema::hasColumn($table, 'routing_tag_ids')) {
            Schema::table($table, function (Blueprint $table) {
                $table->json('routing_tag_ids')->nullable()->after('slug');
            });
        }

        if (! Schema::hasColumn($table, 'routing_department_ids')) {
            Schema::table($table, function (Blueprint $table) {
                $table->json('routing_department_ids')->nullable()->after('routing_tag_ids');
            });
        }
    }

    public function down(): void
    {
        $table = Escalated::table('skills');

        $columns = array_values(array_filter(
            ['routing_tag_ids', 'routing_department_ids'],
            fn (string $column) => Schema::hasColumn($table, $column),
        ));

        if ($columns !== []) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                $blueprint->dropColumn($columns);
            });
        }
    }
};

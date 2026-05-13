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

        Schema::table($prefix.'skills', function (Blueprint $table) use ($prefix) {
            if (! Schema::hasColumn($prefix.'skills', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
        });

        $agentSkill = $prefix.'agent_skill';

        Schema::table($agentSkill, function (Blueprint $table) use ($agentSkill) {
            if (! Schema::hasColumn($agentSkill, 'created_at')) {
                $table->timestamps();
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                sprintf(
                    'ALTER TABLE `%s` MODIFY `proficiency` TINYINT UNSIGNED NOT NULL DEFAULT 3',
                    $agentSkill,
                ),
            );
        }
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        $agentSkill = $prefix.'agent_skill';

        Schema::table($prefix.'skills', function (Blueprint $table) use ($prefix) {
            if (Schema::hasColumn($prefix.'skills', 'description')) {
                $table->dropColumn('description');
            }
        });

        Schema::table($agentSkill, function (Blueprint $table) use ($agentSkill) {
            if (Schema::hasColumn($agentSkill, 'created_at')) {
                $table->dropTimestamps();
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                sprintf(
                    'ALTER TABLE `%s` MODIFY `proficiency` INT UNSIGNED NOT NULL DEFAULT 1',
                    $agentSkill,
                ),
            );
        }
    }
};

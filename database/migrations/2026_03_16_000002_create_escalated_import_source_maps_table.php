<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'import_source_maps', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->uuid('import_job_id');
            $table->string('entity_type', 50);
            $table->string('source_id', 255);
            $table->string('escalated_id', 255);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('import_job_id')
                ->references('id')
                ->on($prefix.'import_jobs')
                ->cascadeOnDelete();

            $table->unique(['import_job_id', 'entity_type', 'source_id'], 'import_source_map_unique');
            $table->index(['import_job_id', 'entity_type']);
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'import_source_maps');
    }
};

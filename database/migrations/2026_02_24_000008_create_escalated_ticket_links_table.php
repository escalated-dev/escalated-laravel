<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'ticket_links', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('parent_ticket_id');
            $table->unsignedBigInteger('child_ticket_id');
            $table->string('link_type'); // problem_incident, parent_child, related
            $table->timestamps();

            $table->foreign('parent_ticket_id')
                ->references('id')
                ->on($prefix.'tickets')
                ->cascadeOnDelete();

            $table->foreign('child_ticket_id')
                ->references('id')
                ->on($prefix.'tickets')
                ->cascadeOnDelete();

            $table->unique(['parent_ticket_id', 'child_ticket_id', 'link_type'], 'parent_child_type_unique');
        });

        Schema::table($prefix.'tickets', function (Blueprint $table) {
            $table->string('type')->default('question')->after('status'); // question, problem, incident, task
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::dropIfExists($prefix.'ticket_links');

        Schema::table($prefix.'tickets', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

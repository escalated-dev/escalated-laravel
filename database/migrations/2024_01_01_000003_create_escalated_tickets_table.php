<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->morphs('requester');
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->string('subject');
            $table->text('description');
            $table->string('status')->default('open')->index();
            $table->string('priority')->default('medium')->index();
            $table->string('channel')->default('web');
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedBigInteger('sla_policy_id')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->boolean('sla_first_response_breached')->default(false);
            $table->boolean('sla_resolution_breached')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'tickets');
    }
};

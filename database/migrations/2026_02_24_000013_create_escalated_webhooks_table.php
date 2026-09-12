<?php

use Escalated\Laravel\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->json('events');
            $table->string('secret')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create($prefix.'webhook_deliveries', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('webhook_id');
            $table->string('event');
            $table->json('payload')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('webhook_id')
                ->references('id')
                ->on($prefix.'webhooks')
                ->cascadeOnDelete();

            $table->index('webhook_id');
            $table->index('event');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::dropIfExists($prefix.'webhook_deliveries');
        Schema::dropIfExists($prefix.'webhooks');
    }
};

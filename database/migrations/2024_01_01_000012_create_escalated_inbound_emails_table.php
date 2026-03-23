<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'inbound_emails', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->string('message_id')->nullable()->unique();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('to_email');
            $table->string('subject');
            $table->text('body_text')->nullable();
            $table->text('body_html')->nullable();
            $table->text('raw_headers')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->unsignedBigInteger('reply_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('adapter');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')
                ->references('id')
                ->on($prefix.'tickets')
                ->nullOnDelete();

            $table->foreign('reply_id')
                ->references('id')
                ->on($prefix.'replies')
                ->nullOnDelete();

            $table->index('from_email');
            $table->index('status');
            $table->index('adapter');
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'inbound_emails');
    }
};

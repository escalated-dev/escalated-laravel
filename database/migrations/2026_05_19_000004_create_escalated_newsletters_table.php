<?php

use Escalated\Laravel\Escalated;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalated_newsletters', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 998);
            $table->string('from_email', 320);
            $table->string('from_name')->nullable();
            $table->string('reply_to', 320)->nullable();
            $table->unsignedBigInteger('target_list_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('theme', 64)->nullable();
            $table->text('body_markdown')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'paused', 'failed'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            Escalated::userForeignColumn($table, 'created_by')->nullable();
            Escalated::userForeignColumn($table, 'sent_by')->nullable();
            $table->unsignedInteger('summary_total')->default(0);
            $table->unsignedInteger('summary_sent')->default(0);
            $table->unsignedInteger('summary_opened')->default(0);
            $table->unsignedInteger('summary_clicked')->default(0);
            $table->unsignedInteger('summary_bounced')->default(0);
            $table->unsignedInteger('summary_complained')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_at');
            $table->index(['status', 'scheduled_at']);
            $table->index('created_by');

            $table->foreign('target_list_id')->references('id')->on('escalated_newsletter_lists')->restrictOnDelete();
            $table->foreign('template_id')->references('id')->on('escalated_newsletter_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalated_newsletters');
    }
};

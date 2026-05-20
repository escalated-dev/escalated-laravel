<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalated_newsletter_deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('newsletter_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('email_at_send', 320);
            $table->enum('status', ['pending', 'queued', 'sent', 'bounced', 'complained', 'suppressed', 'failed'])->default('pending');
            $table->string('tracking_token', 40)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('last_clicked_at')->nullable();
            $table->unsignedInteger('clicks_count')->default(0);
            $table->text('bounce_reason')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->boolean('is_test')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['newsletter_id', 'status']);
            $table->index('contact_id');
            $table->index(['status', 'claimed_at']);

            $table->foreign('newsletter_id')->references('id')->on('escalated_newsletters')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('escalated_contacts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalated_newsletter_deliveries');
    }
};

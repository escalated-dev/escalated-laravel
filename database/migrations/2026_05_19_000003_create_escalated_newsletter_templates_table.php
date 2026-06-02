<?php

use Escalated\Laravel\Escalated;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalated_newsletter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('theme', 64)->default('default');
            $table->string('subject_template', 998)->nullable();
            $table->text('body_markdown');
            $table->json('merge_fields_schema')->nullable();
            Escalated::userForeignColumn($table, 'created_by')->nullable();
            $table->timestamps();

            $table->index('theme');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalated_newsletter_templates');
    }
};

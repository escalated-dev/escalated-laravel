<?php

use Escalated\Laravel\Escalated;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Escalated::schema()->create(Escalated::table('attachment_migrations'), function (Blueprint $table) {
            $table->id();
            // No cascading foreign key: recovery must survive an attachment being deleted.
            $table->unsignedBigInteger('attachment_id')->unique();
            $table->string('source_disk');
            $table->text('source_path');
            $table->string('destination_disk');
            $table->text('destination_path');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Escalated::db()->table(Escalated::table('attachment_migrations'))->exists()) {
            throw new RuntimeException('Finish pending attachment migrations before removing their recovery journal.');
        }

        Escalated::schema()->dropIfExists(Escalated::table('attachment_migrations'));
    }
};

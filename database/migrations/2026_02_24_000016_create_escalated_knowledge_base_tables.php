<?php

use Escalated\Laravel\Escalated;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::create($prefix.'article_categories', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')
                ->references('id')
                ->on($prefix.'article_categories')
                ->nullOnDelete();
        });

        Schema::create($prefix.'articles', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body')->nullable();
            $table->string('status')->default('draft');
            Escalated::userForeignColumn($table, 'author_id')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('not_helpful_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('category_id')
                ->references('id')
                ->on($prefix.'article_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');

        Schema::dropIfExists($prefix.'articles');
        Schema::dropIfExists($prefix.'article_categories');
    }
};

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

        Schema::create($prefix.'roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create($prefix.'permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group');
            $table->text('description')->nullable();
        });

        Schema::create($prefix.'role_permission', function (Blueprint $table) use ($prefix) {
            $table->foreignId('role_id')->constrained($prefix.'roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained($prefix.'permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create($prefix.'role_user', function (Blueprint $table) use ($prefix) {
            Escalated::userForeignColumn($table, 'user_id');
            $table->foreignId('role_id')->constrained($prefix.'roles')->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        $prefix = config('escalated.table_prefix', 'escalated_');
        Schema::dropIfExists($prefix.'role_user');
        Schema::dropIfExists($prefix.'role_permission');
        Schema::dropIfExists($prefix.'permissions');
        Schema::dropIfExists($prefix.'roles');
    }
};

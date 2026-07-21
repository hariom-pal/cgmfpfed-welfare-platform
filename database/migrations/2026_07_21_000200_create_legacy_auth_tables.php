<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_type', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('type');
        });

        Schema::create('priviledge', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('priviledge_name');
        });

        Schema::create('role_priviledge', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('role_id')->index();
            $table->unsignedInteger('permission_id')->index();

            $table->unique(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_priviledge');
        Schema::dropIfExists('priviledge');
        Schema::dropIfExists('user_type');
    }
};

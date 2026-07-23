<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 60)->index();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['module', 'name']);
        });

        Schema::create('export_template_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('export_templates')->cascadeOnDelete();
            $table->string('field_name', 100);
            $table->string('display_name');
            $table->unsignedInteger('column_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['template_id', 'field_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_template_fields');
        Schema::dropIfExists('export_templates');
    }
};

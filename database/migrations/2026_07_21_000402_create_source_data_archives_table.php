<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_data_archives', function (Blueprint $table): void {
            $table->id();
            $table->string('source_table', 80);
            $table->string('source_primary_key', 120)->nullable();
            $table->json('payload');
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['source_table', 'source_primary_key'], 'source_archive_table_pk_unique');
            $table->index('source_table');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_data_archives');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheme_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheme_id')->constrained('schemes')->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['scheme_id', 'document_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheme_documents');
    }
};

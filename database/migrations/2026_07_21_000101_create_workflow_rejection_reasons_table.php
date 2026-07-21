<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_rejection_reasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_status_id')->constrained('workflow_statuses')->cascadeOnDelete();
            $table->foreignId('rejection_reason_id')->constrained('rejection_reasons')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['workflow_status_id', 'rejection_reason_id'], 'wf_status_reason_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_rejection_reasons');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholarship_workflow_batches', function (Blueprint $table): void {
            $table->string('axis_file_path')->nullable()->after('mom_file_path');
            $table->timestamp('axis_file_generated_at')->nullable()->after('axis_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('scholarship_workflow_batches', function (Blueprint $table): void {
            $table->dropColumn(['axis_file_path', 'axis_file_generated_at']);
        });
    }
};

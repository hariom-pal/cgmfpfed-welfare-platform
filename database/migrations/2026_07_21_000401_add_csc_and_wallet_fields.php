<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('csc_id')->nullable()->unique()->after('mobile');
            $table->json('csc_payload')->nullable()->after('csc_id');
        });

        Schema::table('scholarship_applications', function (Blueprint $table): void {
            $table->timestamp('wallet_paid_at')->nullable()->after('submitted_by');
        });
    }

    public function down(): void
    {
        Schema::table('scholarship_applications', function (Blueprint $table): void {
            $table->dropColumn('wallet_paid_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['csc_id']);
            $table->dropColumn(['csc_id', 'csc_payload']);
        });
    }
};

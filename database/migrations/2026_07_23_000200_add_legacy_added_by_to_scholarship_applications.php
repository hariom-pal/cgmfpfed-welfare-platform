<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholarship_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('scholarship_applications', 'legacy_added_by')) {
                $table->string('legacy_added_by', 255)->nullable()->after('applicant_user_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('scholarship_applications', function (Blueprint $table): void {
            if (Schema::hasColumn('scholarship_applications', 'legacy_added_by')) {
                $table->dropColumn('legacy_added_by');
            }
        });
    }
};

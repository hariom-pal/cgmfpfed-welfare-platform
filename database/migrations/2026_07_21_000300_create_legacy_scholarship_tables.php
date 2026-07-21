<?php

declare(strict_types=1);

use App\Support\LegacyScholarshipSql;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $sql = LegacyScholarshipSql::read();

        foreach (LegacyScholarshipSql::createStatements($sql) as $statement) {
            DB::unprepared(LegacyScholarshipSql::prefixedStatement($statement));
        }

        foreach (LegacyScholarshipSql::alterStatements($sql) as $statement) {
            DB::unprepared(LegacyScholarshipSql::prefixedStatement($statement));
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $sql = LegacyScholarshipSql::read();
        $prefix = (string) config('legacy_database.table_prefix', 'legacy_');

        foreach (array_reverse(LegacyScholarshipSql::tableNames($sql)) as $table) {
            Schema::dropIfExists($prefix.$table);
        }
    }
};

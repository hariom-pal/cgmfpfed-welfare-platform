<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\LegacyScholarshipSql;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class LegacyScholarshipDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $sql = LegacyScholarshipSql::read();
        $prefix = (string) config('legacy_database.table_prefix', 'legacy_');

        try {
            Schema::disableForeignKeyConstraints();

            $createdTables = [];
            foreach (LegacyScholarshipSql::createStatements($sql) as $statement) {
                $table = LegacyScholarshipSql::sourceTableName($statement);

                if ($table !== null && ! Schema::hasTable($prefix.$table)) {
                    DB::unprepared(LegacyScholarshipSql::prefixedStatement($statement));
                    $createdTables[$table] = true;
                }
            }

            foreach (LegacyScholarshipSql::alterStatements($sql) as $statement) {
                $table = LegacyScholarshipSql::sourceTableName($statement);

                if ($table !== null && isset($createdTables[$table])) {
                    DB::unprepared(LegacyScholarshipSql::prefixedStatement($statement));
                }
            }

            foreach (LegacyScholarshipSql::tableNames($sql) as $table) {
                DB::table($prefix.$table)->truncate();
            }

            foreach (LegacyScholarshipSql::insertStatements($sql) as $statement) {
                DB::unprepared(mb_scrub(LegacyScholarshipSql::prefixedStatement($statement), 'UTF-8'));
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}

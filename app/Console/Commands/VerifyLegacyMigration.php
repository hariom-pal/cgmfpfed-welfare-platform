<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\LegacyScholarshipSql;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('legacy:verify-migration')]
#[Description('Verify Scholarship SQL source rows are archived and temporary import tables are removed')]
final class VerifyLegacyMigration extends Command
{
    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->warn('Migration verification is only available on the MySQL import database.');

            return self::SUCCESS;
        }

        $sql = LegacyScholarshipSql::read();
        $expected = array_fill_keys(LegacyScholarshipSql::tableNames($sql), 0);

        foreach (LegacyScholarshipSql::insertStatements($sql) as $statement) {
            $table = LegacyScholarshipSql::sourceTableName($statement);

            if ($table !== null) {
                $expected[$table] = ($expected[$table] ?? 0) + LegacyScholarshipSql::recordCountInInsert($statement);
            }
        }

        $rows = [];
        $failed = false;

        foreach ($expected as $table => $expectedCount) {
            $archivedCount = Schema::hasTable('source_data_archives')
                ? DB::table('source_data_archives')->where('source_table', $table)->count()
                : null;
            $matches = $archivedCount === $expectedCount;
            $failed = $failed || ! $matches;

            $rows[] = [
                $table,
                $expectedCount,
                $archivedCount ?? 'missing',
                $matches ? 'OK' : 'MISMATCH',
            ];
        }

        $temporaryImportTables = DB::table('information_schema.tables')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', 'like', 'legacy\_%')
            ->count();

        if ($temporaryImportTables > 0) {
            $failed = true;
            $this->error("Temporary import tables remaining: {$temporaryImportTables}");
        } else {
            $this->info('Temporary import tables remaining: 0');
        }

        $this->table(['Source table', 'SQL rows', 'Archived rows', 'Status'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

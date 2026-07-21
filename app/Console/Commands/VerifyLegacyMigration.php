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
#[Description('Compare legacy Scholarship SQL row counts with imported legacy mirror tables')]
final class VerifyLegacyMigration extends Command
{
    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->warn('Legacy row-count verification is only available on the MySQL import database.');

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

        $prefix = (string) config('legacy_database.table_prefix', 'legacy_');
        $rows = [];
        $failed = false;

        foreach ($expected as $table => $expectedCount) {
            $mirrorTable = $prefix.$table;
            $actualCount = Schema::hasTable($mirrorTable) ? DB::table($mirrorTable)->count() : null;
            $matches = $actualCount === $expectedCount;
            $failed = $failed || ! $matches;

            $rows[] = [
                $table,
                $mirrorTable,
                $expectedCount,
                $actualCount ?? 'missing',
                $matches ? 'OK' : 'MISMATCH',
            ];
        }

        $this->table(['Source table', 'Mirror table', 'SQL rows', 'Imported rows', 'Status'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

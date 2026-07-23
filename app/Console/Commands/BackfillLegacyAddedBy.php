<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\LegacyScholarshipSql;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('scholarship:backfill-legacy-added-by')]
#[Description('Backfill scholarship_applications.legacy_added_by (legacy CSC ID) from scholarship.sql for applications migrated before this column existed. Does not touch applicant_user_id.')]
final class BackfillLegacyAddedBy extends Command
{
    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('This command requires the mysql driver.');

            return self::FAILURE;
        }

        $table = ((string) config('legacy_database.table_prefix', 'legacy_')).'application';
        $alreadyExisted = Schema::hasTable($table);

        if (! $alreadyExisted) {
            $this->components->info('Temporarily replaying the legacy `application` table from scholarship.sql ...');
            $this->replayApplicationTable($table);
        }

        $updated = 0;
        DB::table($table)
            ->select('id', 'added_by')
            ->orderBy('id')
            ->lazy(1000)
            ->each(function (object $row) use (&$updated): void {
                $updated += DB::table('scholarship_applications')
                    ->where('legacy_application_id', (int) $row->id)
                    ->whereNull('legacy_added_by')
                    ->update(['legacy_added_by' => (string) $row->added_by]);
            });

        if (! $alreadyExisted) {
            Schema::dropIfExists($table);
        }

        $this->components->info("Backfilled legacy_added_by on {$updated} application(s).");

        return self::SUCCESS;
    }

    private function replayApplicationTable(string $table): void
    {
        $sql = LegacyScholarshipSql::read();

        foreach (LegacyScholarshipSql::createStatements($sql) as $statement) {
            if (LegacyScholarshipSql::sourceTableName($statement) === 'application') {
                DB::unprepared(LegacyScholarshipSql::prefixedStatement($statement));

                break;
            }
        }

        if (! Schema::hasTable($table)) {
            throw new \RuntimeException('Legacy `application` CREATE TABLE statement was not found in scholarship.sql.');
        }

        foreach (LegacyScholarshipSql::insertStatements($sql) as $statement) {
            if (LegacyScholarshipSql::sourceTableName($statement) === 'application') {
                DB::unprepared(mb_scrub(LegacyScholarshipSql::prefixedStatement($statement), 'UTF-8'));
            }
        }
    }
}
